<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\PaperGrammage;
use App\Models\PaperStock;
use App\Models\PaperSupplier;
use App\Models\StockMovement;
use App\Services\Inventory\PaperInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paper stock (مخزن الورق): items, receipts from suppliers, stock counts.
 * Consumption happens automatically when a job is approved.
 */
class InventoryController extends Controller
{
    public function index(): Response
    {
        $stocks = PaperStock::query()
            ->with('paperGrammage.paperType')
            ->get()
            ->sortBy(fn (PaperStock $s) => [$s->paperGrammage->paperType->name, $s->paperGrammage->gsm, (float) $s->sheet_width_cm])
            ->values();

        return Inertia::render('inventory/index', [
            'stocks' => $stocks->map(fn (PaperStock $s) => self::row($s)),
            'grammages' => PaperGrammage::query()->with('paperType')->get()
                ->sortBy(fn (PaperGrammage $g) => [$g->paperType->name, $g->gsm])
                ->map(fn (PaperGrammage $g) => [
                    'value' => (string) $g->id,
                    'label' => "{$g->paperType->name} {$g->gsm} جم",
                    'sheet' => [$g->paperType->sheet_width_cm, $g->paperType->sheet_height_cm],
                ])->values(),
        ]);
    }

    public function show(PaperStock $stock): Response
    {
        $stock->load('paperGrammage.paperType');

        return Inertia::render('inventory/show', [
            'stock' => self::row($stock),
            'movements' => $stock->movements()->with(['supplier:id,name', 'user:id,name'])
                ->paginate(30)
                ->through(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'typeLabel' => $m->type->label(),
                    'quantity' => $m->quantity,
                    'balanceAfter' => $m->balance_after,
                    'jobId' => $m->job_id,
                    'supplier' => $m->supplier?->name,
                    'user' => $m->user?->name,
                    'note' => $m->note,
                    'at' => $m->created_at?->toIso8601String(),
                ]),
            'suppliers' => PaperSupplier::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (PaperSupplier $s) => ['value' => (string) $s->id, 'label' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paper_grammage_id' => ['required', 'integer', Rule::exists('paper_grammages', 'id')],
            'sheet_width_cm' => ['required', 'numeric', 'min:10', 'max:300'],
            'sheet_height_cm' => ['required', 'numeric', 'min:10', 'max:300'],
            'quantity_sheets' => ['required', 'integer', 'min:0', 'max:10000000'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        [$w, $h] = PaperInventory::normalize((float) $data['sheet_width_cm'], (float) $data['sheet_height_cm']);
        if (PaperInventory::find((int) $data['paper_grammage_id'], $w, $h)) {
            return back()->withErrors(['paper_grammage_id' => 'الصنف ده بالمقاس ده موجود في المخزن بالفعل']);
        }

        $stock = PaperStock::create([
            'paper_grammage_id' => $data['paper_grammage_id'],
            'sheet_width_cm' => $w,
            'sheet_height_cm' => $h,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'location' => $data['location'] ?? null,
        ]);
        if ($data['quantity_sheets'] > 0) {
            $stock = PaperInventory::move($stock, StockMovementType::Adjustment, (int) $data['quantity_sheets'], $request->user(), 'رصيد أول المدة');
        }
        ActivityLog::record('paper_stock', $stock->id, 'created', 'إضافة صنف للمخزن: '.$stock->label());
        $this->toast('تمت إضافة الصنف');

        return to_route('inventory.show', $stock);
    }

    public function update(Request $request, PaperStock $stock): RedirectResponse
    {
        $stock->update($request->validate([
            'reorder_level' => ['required', 'integer', 'min:0', 'max:10000000'],
            'location' => ['nullable', 'string', 'max:100'],
        ]));
        $this->toast('تم الحفظ');

        return back();
    }

    public function receive(Request $request, PaperStock $stock): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'paper_supplier_id' => ['nullable', 'integer', Rule::exists('paper_suppliers', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $supplierId = isset($data['paper_supplier_id']) ? (int) $data['paper_supplier_id'] : null;
        $stock = PaperInventory::move($stock, StockMovementType::Receipt, (int) $data['quantity'], $request->user(), $data['note'] ?? null, null, $supplierId);
        $this->toast("تم استلام {$data['quantity']} فرخ — الرصيد {$stock->quantity_sheets}");

        return back();
    }

    /** Stock count: set the balance to what was physically counted. */
    public function adjust(Request $request, PaperStock $stock): RedirectResponse
    {
        $data = $request->validate([
            'counted' => ['required', 'integer', 'min:0', 'max:10000000'],
            'note' => ['required', 'string', 'max:500'],
        ], ['note.required' => 'اكتب سبب التسوية (جرد، تالف، ...)']);

        $delta = (int) $data['counted'] - $stock->quantity_sheets;
        if ($delta === 0) {
            $this->toast('الرصيد مطابق — مفيش تسوية');

            return back();
        }

        PaperInventory::move($stock, StockMovementType::Adjustment, $delta, $request->user(), $data['note']);
        ActivityLog::record('paper_stock', $stock->id, 'adjusted', sprintf('تسوية %s: %+d فرخ (%s)', $stock->label(), $delta, $data['note']));
        $this->toast(sprintf('تمت التسوية (%+d فرخ)', $delta));

        return back();
    }

    /** @return array{id: int, label: string, paper: string, gsm: int, size: string, quantity: int, reorderLevel: int, location: string|null, low: bool} */
    public static function row(PaperStock $s): array
    {
        return [
            'id' => $s->id,
            'label' => $s->label(),
            'paper' => $s->paperGrammage->paperType->name,
            'gsm' => $s->paperGrammage->gsm,
            'size' => sprintf('%s×%s', (float) $s->sheet_width_cm, (float) $s->sheet_height_cm),
            'quantity' => $s->quantity_sheets,
            'reorderLevel' => $s->reorder_level,
            'location' => $s->location,
            'low' => $s->isLow(),
        ];
    }
}

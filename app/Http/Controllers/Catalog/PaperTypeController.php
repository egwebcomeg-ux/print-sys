<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\PaperCategory;
use App\Http\Controllers\Controller;
use App\Models\PaperSupplier;
use App\Models\PaperType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaperTypeController extends Controller
{
    public function index(): Response
    {
        $types = PaperType::query()
            ->with(['grammages' => fn ($q) => $q->withCount('prices')->with('cheapestPrice.supplier')])
            ->orderBy('name')
            ->get()
            ->map(fn (PaperType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'category' => $type->category,
                'categoryLabel' => $type->category->label(),
                'sheetSize' => "{$type->sheet_width_cm}×{$type->sheet_height_cm}",
                'grammages' => $type->grammages->map(fn ($g) => [
                    'id' => $g->id,
                    'gsm' => $g->gsm,
                    'pricesCount' => $g->prices_count,
                    'cheapest' => $g->cheapestPrice ? [
                        'supplierName' => $g->cheapestPrice->supplier->name,
                        'pricePerTonEgp' => (float) $g->cheapestPrice->price_per_ton_egp,
                    ] : null,
                ]),
            ]);

        return Inertia::render('paper-types/index', ['paperTypes' => $types]);
    }

    public function create(): Response
    {
        return Inertia::render('paper-types/form', [
            'paperType' => null,
            'categories' => PaperCategory::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = PaperType::query()->create($this->validated($request));
        $this->toast('تم إضافة نوع الورق — ضيف الجرامات والأسعار');

        return to_route('paper-types.edit', $type);
    }

    /**
     * The edit page also manages the type's grammages and every supplier's
     * price per grammage (multi-supplier comparison).
     */
    public function edit(PaperType $paperType): Response
    {
        $paperType->load('grammages.prices.supplier');

        return Inertia::render('paper-types/form', [
            'paperType' => [
                'id' => $paperType->id,
                'name' => $paperType->name,
                'category' => $paperType->category,
                'sheet_width_cm' => $paperType->sheet_width_cm,
                'sheet_height_cm' => $paperType->sheet_height_cm,
                'grammages' => $paperType->grammages->map(fn ($g) => [
                    'id' => $g->id,
                    'gsm' => $g->gsm,
                    'prices' => $g->prices->map(fn ($p) => [
                        'id' => $p->id,
                        'supplierId' => $p->paper_supplier_id,
                        'supplierName' => $p->supplier->name,
                        'pricePerTonEgp' => (float) $p->price_per_ton_egp,
                        'priceAsOf' => $p->price_as_of?->toDateString(),
                    ]),
                ]),
            ],
            'categories' => PaperCategory::options(),
            'suppliers' => PaperSupplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, PaperType $paperType): RedirectResponse
    {
        $paperType->update($this->validated($request));
        $this->toast('تم حفظ نوع الورق');

        return to_route('paper-types.edit', $paperType);
    }

    public function destroy(PaperType $paperType): RedirectResponse
    {
        if ($this->deleteSafely(fn () => $paperType->delete(), 'مينفعش تمسح نوع ورق مستخدم في شغلانات')) {
            $this->toast('تم مسح نوع الورق');
        }

        return to_route('paper-types.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(PaperCategory::class)],
            'sheet_width_cm' => ['required', 'integer', 'min:10', 'max:300'],
            'sheet_height_cm' => ['required', 'integer', 'min:10', 'max:300'],
        ]);
    }
}

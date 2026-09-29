<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\PaperSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaperSupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('paper-suppliers/index', [
            'suppliers' => PaperSupplier::query()->withCount('prices')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('paper-suppliers/form', ['supplier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        PaperSupplier::query()->create($this->validated($request));
        $this->toast('تم إضافة المورد');

        return to_route('paper-suppliers.index');
    }

    public function edit(PaperSupplier $paperSupplier): Response
    {
        return Inertia::render('paper-suppliers/form', ['supplier' => $paperSupplier]);
    }

    public function update(Request $request, PaperSupplier $paperSupplier): RedirectResponse
    {
        $paperSupplier->update($this->validated($request, $paperSupplier));
        $this->toast('تم حفظ بيانات المورد');

        return to_route('paper-suppliers.index');
    }

    public function destroy(PaperSupplier $paperSupplier): RedirectResponse
    {
        // Deleting a supplier cascades to its prices (by schema design).
        $paperSupplier->delete();
        $this->toast('تم مسح المورد وأسعاره');

        return to_route('paper-suppliers.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PaperSupplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:paper_suppliers,name'.($supplier ? ",{$supplier->id}" : '')],
            'contact_note' => ['nullable', 'string', 'max:255'],
            'performance_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}

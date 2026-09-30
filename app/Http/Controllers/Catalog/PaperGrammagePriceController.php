<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\PaperPriceChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaperGrammagePriceController extends Controller
{
    /**
     * Add a supplier's price for a grammage, or update it if that supplier
     * already has one (one price per grammage+supplier). `price_as_of` is
     * stamped only here, so unrelated edits never make a price look fresh.
     */
    public function store(Request $request, PaperGrammage $grammage): RedirectResponse
    {
        $data = $request->validate([
            'paper_supplier_id' => ['required', 'integer', 'exists:paper_suppliers,id'],
            'price_per_ton_egp' => ['required', 'numeric', 'min:1', 'max:10000000'],
        ]);

        $old = PaperGrammagePrice::query()
            ->where('paper_grammage_id', $grammage->id)
            ->where('paper_supplier_id', $data['paper_supplier_id'])
            ->value('price_per_ton_egp');

        $price = PaperGrammagePrice::query()->updateOrCreate(
            ['paper_grammage_id' => $grammage->id, 'paper_supplier_id' => $data['paper_supplier_id']],
            ['price_per_ton_egp' => $data['price_per_ton_egp'], 'price_as_of' => now()],
        );

        // Keep the history so price moves per supplier can be reviewed.
        if ($old === null || (float) $old !== (float) $data['price_per_ton_egp']) {
            PaperPriceChange::query()->create([
                'paper_grammage_price_id' => $price->id,
                'paper_grammage_id' => $grammage->id,
                'paper_supplier_id' => $data['paper_supplier_id'],
                'old_price_egp' => $old,
                'new_price_egp' => $data['price_per_ton_egp'],
                'user_id' => $request->user()->id,
            ]);
        }

        $this->toast('تم حفظ السعر');

        return back();
    }

    public function destroy(PaperGrammagePrice $price): RedirectResponse
    {
        $price->delete();
        $this->toast('تم مسح السعر');

        return back();
    }
}

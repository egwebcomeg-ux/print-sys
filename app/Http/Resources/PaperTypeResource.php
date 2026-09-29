<?php

namespace App\Http\Resources;

use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\PaperType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shaped like `PaperType` in the calculators: type → grammages → every
 * supplier's price (multi-supplier). Load with `grammages.prices.supplier`.
 *
 * @mixin PaperType
 */
class PaperTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'standardSheetSize' => [
                'widthCm' => $this->sheet_width_cm,
                'heightCm' => $this->sheet_height_cm,
            ],
            'grammages' => $this->grammages->map(fn (PaperGrammage $grammage) => [
                'id' => (string) $grammage->id,
                'gsm' => $grammage->gsm,
                'prices' => $grammage->prices->map(fn (PaperGrammagePrice $price) => [
                    'id' => (string) $price->id,
                    'supplierName' => $price->supplier->name,
                    'pricePerTonEgp' => (float) $price->price_per_ton_egp,
                ])->values(),
            ])->values(),
        ];
    }
}

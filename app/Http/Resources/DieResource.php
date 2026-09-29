<?php

namespace App\Http\Resources;

use App\Models\CuttingDie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shaped like `DieCutTool` in QuickBoxPricingCalculator.tsx.
 *
 * @mixin CuttingDie
 */
class DieResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'lengthCm' => (float) $this->length_cm,
            'widthCm' => (float) $this->width_cm,
            'depthCm' => (float) $this->depth_cm,
            'closureType' => $this->closure_type->value,
            'rackLocation' => $this->rack_location ?? '',
            'upsOnCutSheet' => $this->ups_on_cut_sheet,
            'cutFraction' => $this->cut_fraction->value,
            'condition' => $this->condition->value,
        ];
    }
}

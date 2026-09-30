<?php

namespace App\Http\Resources;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shaped like `SavedJobSpec` for the "كرر نفس الشغلانة" shortcut. Load with
 * `customer` and `paperGrammage`. The margin is intentionally not carried
 * over — a repeated job starts from the default margin.
 *
 * @mixin Job
 */
class SavedJobSpecResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'label' => "{$this->customer->name} — {$this->displayName()}",
            'boxTypeId' => $this->box_type?->value,
            'boxShapeId' => $this->box_shape?->value,
            'doubleWallOn' => $this->quote_snapshot['doubleWallOn'] ?? null,
            'lengthCm' => (float) $this->length_cm,
            'widthCm' => (float) $this->width_cm,
            'depthCm' => (float) $this->depth_cm,
            'quantity' => (int) $this->quantity,
            'paperTypeId' => (string) $this->paperGrammage?->paper_type_id,
            'grammageId' => (string) $this->paper_grammage_id,
            'supplierPriceId' => $this->paper_grammage_price_id ? (string) $this->paper_grammage_price_id : null,
            'printColors' => $this->print_colors,
            'lamination' => $this->lamination->value,
            'isUsingExistingDie' => $this->is_using_existing_die,
            'selectedDieId' => $this->die_id ? (string) $this->die_id : null,
        ];
    }
}

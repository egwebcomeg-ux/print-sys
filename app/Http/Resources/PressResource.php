<?php

namespace App\Http\Resources;

use App\Models\Press;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shaped like `Press` in PressRoutingSelector.tsx.
 *
 * @mixin Press
 */
class PressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'isInternal' => $this->is_internal,
            'maxColors' => $this->max_colors,
            'supportedCutFractions' => array_values($this->supported_cut_fractions ?? []),
            // Empty = accepts every paper category.
            'supportedPaperCategories' => array_values($this->supported_paper_categories ?? []),
            'currentBacklogDays' => $this->current_backlog_days,
            'contactNote' => $this->contact_note,
        ];
    }
}

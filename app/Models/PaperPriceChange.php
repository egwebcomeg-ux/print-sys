<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paper_grammage_price_id', 'paper_grammage_id', 'paper_supplier_id', 'old_price_egp', 'new_price_egp', 'user_id'])]
class PaperPriceChange extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['old_price_egp' => 'decimal:2', 'new_price_egp' => 'decimal:2', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<PaperGrammage, $this> */
    public function grammage(): BelongsTo
    {
        return $this->belongsTo(PaperGrammage::class, 'paper_grammage_id');
    }

    /** @return BelongsTo<PaperSupplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PaperSupplier::class, 'paper_supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

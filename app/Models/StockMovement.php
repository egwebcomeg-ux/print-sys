<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $paper_stock_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $balance_after
 * @property int|null $job_id
 * @property int|null $paper_supplier_id
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['paper_stock_id', 'type', 'quantity', 'balance_after', 'job_id', 'paper_supplier_id', 'user_id', 'note'])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /** @return BelongsTo<PaperStock, $this> */
    public function paperStock(): BelongsTo
    {
        return $this->belongsTo(PaperStock::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'subject_type', 'subject_id', 'action', 'description', 'properties'])]
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an entry. The acting user defaults to the authenticated one;
     * pass null explicitly for system actions (queued Odoo sync).
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $subjectType, ?int $subjectId, string $action, string $description, array $properties = [], ?User $user = null): self
    {
        return self::query()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}

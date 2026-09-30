<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A design / artwork file attached to a job (stored on the private disk).
 *
 * @property int $id
 * @property int $job_id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime
 * @property int $size
 * @property int $version
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['job_id', 'user_id', 'original_name', 'path', 'mime', 'size', 'version', 'note'])]
class JobFile extends Model
{
    /** Allowed extensions for artwork uploads. */
    public const EXTENSIONS = ['pdf', 'ai', 'eps', 'psd', 'png', 'jpg', 'jpeg', 'svg', 'zip', 'rar', 'cdr', 'tif', 'tiff'];

    /** Max upload size in KB (50 MB). */
    public const MAX_KB = 51200;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

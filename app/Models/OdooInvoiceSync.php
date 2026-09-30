<?php

namespace App\Models;

use App\Enums\OdooSyncStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per invoicing attempt (API call, retry, or manual fallback), with
 * the request/response payloads kept for auditing.
 *
 * @property int $id
 * @property int $job_id
 * @property string|null $odoo_invoice_id
 * @property OdooSyncStatus $status
 * @property array<array-key, mixed>|null $request_payload
 * @property array<array-key, mixed>|null $response_payload
 * @property string|null $error_message
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['job_id', 'odoo_invoice_id', 'status', 'request_payload', 'response_payload', 'error_message', 'synced_at'])]
class OdooInvoiceSync extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OdooSyncStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function isManual(): bool
    {
        return (bool) ($this->response_payload['manual'] ?? false);
    }
}

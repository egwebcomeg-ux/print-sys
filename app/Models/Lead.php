<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property string $contact_name
 * @property string|null $company_name
 * @property string|null $phone
 * @property string|null $source
 * @property LeadStatus $status
 * @property int|null $expected_quantity
 * @property string|null $notes
 * @property int|null $owner_user_id
 * @property int|null $converted_job_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'customer_id', 'contact_name', 'company_name', 'phone', 'source', 'status',
    'expected_quantity', 'notes', 'owner_user_id', 'converted_job_id',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'expected_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<Job, $this> */
    public function convertedJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'converted_job_id');
    }
}

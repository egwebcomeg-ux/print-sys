<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OdooSyncStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Sent = 'sent';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'في الانتظار',
            self::Sent => 'اتبعتت',
            self::Success => 'نجحت',
            self::Failed => 'فشلت',
        };
    }
}

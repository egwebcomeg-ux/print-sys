<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DieCondition: string
{
    use HasOptions;

    case Ready = 'ready';
    case NeedsRubber = 'needs_rubber';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'جاهزة',
            self::NeedsRubber => 'محتاجة كاوتش',
            self::Maintenance => 'صيانة',
        };
    }
}

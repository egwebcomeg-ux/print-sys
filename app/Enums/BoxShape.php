<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BoxShape: string
{
    use HasOptions;

    case ReverseTuckEnd = 'reverse_tuck_end';
    case StraightTuckEnd = 'straight_tuck_end';
    case AutoLockBottom = 'auto_lock_bottom';
    case PillowBag = 'pillow_bag';
    case LidAndBase = 'lid_and_base';

    public function label(): string
    {
        return match ($this) {
            self::ReverseTuckEnd => 'لسان عكسي',
            self::StraightTuckEnd => 'لسان مستقيم',
            self::AutoLockBottom => 'قاع أوتوماتيك',
            self::PillowBag => 'مخدة',
            self::LidAndBase => 'قاع وغطاء',
        };
    }
}

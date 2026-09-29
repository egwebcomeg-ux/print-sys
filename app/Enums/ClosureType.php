<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClosureType: string
{
    use HasOptions;

    case ReverseTuck = 'reverse_tuck';
    case StraightTuck = 'straight_tuck';
    case AutoBottom = 'auto_bottom';
    case SnapLock = 'snap_lock';

    public function label(): string
    {
        return match ($this) {
            self::ReverseTuck => 'لسان عكسي',
            self::StraightTuck => 'لسان مستقيم',
            self::AutoBottom => 'قاع أوتوماتيك',
            self::SnapLock => 'قفل سناب',
        };
    }
}

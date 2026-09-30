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
    case FrontLock = 'front_lock';
    case GluedCorners = 'glued_corners';

    public function label(): string
    {
        return match ($this) {
            self::ReverseTuck => 'لسان عكسي',
            self::StraightTuck => 'لسان مستقيم',
            self::AutoBottom => 'قاع أوتوماتيك',
            self::SnapLock => 'قفل سناب',
            self::FrontLock => 'قفل أمامي (بيتزا / تليفون)',
            self::GluedCorners => 'لصق أركان (٦ بونط)',
        };
    }
}

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
    case PizzaBox = 'pizza_box';
    case PhoneBox = 'phone_box';
    case GluedTrayLid = 'glued_tray_lid';
    case SelfLockTrayLid = 'self_lock_tray_lid';

    /** Delivered flat and folded/locked by the customer — no gluing. */
    public function isGlued(): bool
    {
        return ! in_array($this, [self::PizzaBox, self::PhoneBox, self::SelfLockTrayLid], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ReverseTuckEnd => 'لسان عكسي',
            self::StraightTuckEnd => 'لسان مستقيم',
            self::AutoLockBottom => 'قاع أوتوماتيك',
            self::PillowBag => 'مخدة',
            self::LidAndBase => 'قاع وغطاء',
            self::PizzaBox => 'علبة بيتزا',
            self::PhoneBox => 'علبة تليفون',
            self::GluedTrayLid => 'صينية بغطا (لصق ٦ بونط)',
            self::SelfLockTrayLid => 'تقفيل ذاتي بغطا',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CutFraction: string
{
    use HasOptions;

    case Full = '1/1';
    case Half = '1/2';
    case Quarter = '1/4';
    case Sixth = '1/6';
    case Eighth = '1/8';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'فرخ كامل 1/1',
            self::Half => 'نص فرخ 1/2',
            self::Quarter => 'ربع فرخ 1/4',
            self::Sixth => 'سدس فرخ 1/6',
            self::Eighth => 'تمن فرخ 1/8',
        };
    }
}

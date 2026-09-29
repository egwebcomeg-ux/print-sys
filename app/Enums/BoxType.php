<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BoxType: string
{
    use HasOptions;

    case Medicine = 'medicine';
    case Candy = 'candy';
    case Cosmetics = 'cosmetics';
    case Food = 'food';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Medicine => 'دواء',
            self::Candy => 'حلويات',
            self::Cosmetics => 'مستحضرات تجميل',
            self::Food => 'أغذية',
            self::General => 'عام',
        };
    }
}

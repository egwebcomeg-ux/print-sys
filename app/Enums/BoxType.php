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
    case Pizza = 'pizza';
    case Phone = 'phone';
    case Oriental = 'oriental';
    case Cake = 'cake';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Medicine => 'دواء',
            self::Candy => 'حلويات',
            self::Cosmetics => 'مستحضرات تجميل',
            self::Food => 'أغذية',
            self::Pizza => 'بيتزا',
            self::Phone => 'تليفون',
            self::Oriental => 'حلويات شرقي',
            self::Cake => 'جاتوه / تورتة',
            self::General => 'عام',
        };
    }
}

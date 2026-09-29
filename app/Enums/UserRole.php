<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case Sales = 'sales';
    case Production = 'production';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'مبيعات',
            self::Production => 'إنتاج',
            self::Admin => 'مدير',
        };
    }
}

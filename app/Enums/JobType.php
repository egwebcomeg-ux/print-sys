<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobType: string
{
    use HasOptions;

    case Box = 'box';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Box => 'علبة',
            self::Manual => 'شغل ورقي يدوي',
        };
    }
}

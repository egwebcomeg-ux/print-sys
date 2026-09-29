<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Lamination: string
{
    use HasOptions;

    case None = 'none';
    case Matte = 'matte';
    case Gloss = 'gloss';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون',
            self::Matte => 'سلوفان مط',
            self::Gloss => 'سلوفان لامع',
        };
    }
}

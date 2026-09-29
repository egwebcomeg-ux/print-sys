<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LeadStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديدة',
            self::Contacted => 'تم التواصل',
            self::Quoted => 'اتبعت عرض سعر',
            self::Won => 'اتكسبت',
            self::Lost => 'اتخسرت',
        };
    }
}

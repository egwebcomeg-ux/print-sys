<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaperCategory: string
{
    use HasOptions;

    case DuplexGreyBack = 'duplex_grey_back';
    case DuplexWhiteBack = 'duplex_white_back';
    case BristolWhiteBack = 'bristol_white_back';
    case KraftLiner = 'kraft_liner';
    case Couche = 'couche';
    case TriplexBoard = 'triplex_board';
    case MicroFlute = 'micro_flute';

    public function label(): string
    {
        return match ($this) {
            self::DuplexGreyBack => 'دوبلكس ظهر رمادي',
            self::DuplexWhiteBack => 'دوبلكس ظهر أبيض',
            self::BristolWhiteBack => 'بريستول ظهر أبيض',
            self::KraftLiner => 'كرافت',
            self::Couche => 'كوشيه',
            self::TriplexBoard => 'تريبلكس',
            self::MicroFlute => 'كرتون مايكرو',
        };
    }
}

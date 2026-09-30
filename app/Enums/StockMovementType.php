<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'استلام',
            self::Consumption => 'صرف لشغلانة',
            self::Adjustment => 'جرد / تسوية',
        };
    }
}

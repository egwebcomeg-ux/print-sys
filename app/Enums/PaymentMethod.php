<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case InstaPay = 'instapay';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::BankTransfer => 'تحويل بنكي',
            self::InstaPay => 'إنستاباي',
            self::Cheque => 'شيك',
            self::Other => 'أخرى',
        };
    }
}

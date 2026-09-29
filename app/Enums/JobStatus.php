<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Quoted => 'اتبعت عرض سعر',
            self::Approved => 'العميل وافق',
            self::InProduction => 'في الإنتاج',
            self::Completed => 'خلصت',
            self::Invoiced => 'اتعملت فاتورة',
        };
    }

    /**
     * Allowed next statuses. Forward-only for now; `invoiced` is only reached
     * through the Odoo sync (or its manual fallback), never by hand.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Quoted],
            self::Quoted => [self::Approved],
            self::Approved => [self::InProduction],
            self::InProduction => [self::Completed],
            self::Completed => [self::Invoiced],
            self::Invoiced => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Statuses a user can move a job to from the UI (i.e. not `invoiced`). */
    public function manualNext(): ?self
    {
        $next = $this->transitions()[0] ?? null;

        return $next === self::Invoiced ? null : $next;
    }
}

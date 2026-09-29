<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobStageStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم تبدأ',
            self::InProgress => 'جارية',
            self::Done => 'خلصت',
        };
    }
}

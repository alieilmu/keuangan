<?php

namespace App\Enums;

enum SavingsGoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Berjalan',
            self::Completed => 'Tercapai',
            self::Paused => 'Dijeda',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

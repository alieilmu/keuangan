<?php

namespace App\Enums;

enum CreditStatus: string
{
    case Active = 'active';
    case PaidOff = 'paid_off';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Berjalan',
            self::PaidOff => 'Lunas',
            self::Closed => 'Ditutup',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case EWallet = 'ewallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Bank => 'Rekening Bank',
            self::EWallet => 'e-Wallet',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }

    /**
     * Signed multiplier applied to an account balance.
     */
    public function sign(): int
    {
        return $this === self::Income ? 1 : -1;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    /** Kaki keluar dari sebuah transfer antar akun. */
    case TransferOut = 'transfer_out';

    /** Kaki masuk dari sebuah transfer antar akun. */
    case TransferIn = 'transfer_in';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
            self::TransferOut => 'Transfer Keluar',
            self::TransferIn => 'Transfer Masuk',
        };
    }

    /**
     * Pengali bertanda untuk saldo akun.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Income, self::TransferIn => 1,
            self::Expense, self::TransferOut => -1,
        };
    }

    /**
     * Transfer hanya memindahkan uang antar akun sendiri, jadi tidak boleh
     * ikut terhitung sebagai pemasukan/pengeluaran pada arus kas, pie chart,
     * maupun agregasi anggaran.
     */
    public function isTransfer(): bool
    {
        return $this === self::TransferIn || $this === self::TransferOut;
    }

    public function affectsCashflow(): bool
    {
        return ! $this->isTransfer();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Tipe yang boleh dipilih user saat mencatat transaksi manual.
     * Kaki transfer hanya boleh dibuat lewat modul Transfer.
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return [self::Income->value, self::Expense->value];
    }

    /** @return array<int, self> */
    public static function manualCases(): array
    {
        return [self::Income, self::Expense];
    }
}

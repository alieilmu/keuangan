<?php

namespace App\Enums;

/**
 * Sifat sebuah perpindahan dana, diturunkan dari jenis akun asal & tujuan.
 * Tidak disimpan di database: selalu dihitung ulang dari akun terkait,
 * sehingga transfer lama pun ikut dikenali tanpa perlu mengubah datanya.
 */
enum TransferKind: string
{
    /** Dari akun non-tunai ke akun tunai: uang ditarik menjadi uang fisik. */
    case Withdrawal = 'withdrawal';

    /** Dari akun tunai ke akun non-tunai: uang fisik disetorkan. */
    case Deposit = 'deposit';

    /** Perpindahan biasa antar akun. */
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Withdrawal => 'Tarik Tunai',
            self::Deposit => 'Setor Tunai',
            self::Transfer => 'Transfer',
        };
    }

    /**
     * Awalan keterangan otomatis untuk kaki keluar & kaki masuk.
     *
     * @return array{0: string, 1: string}
     */
    public function prefixes(): array
    {
        return match ($this) {
            self::Withdrawal => ['Tarik tunai ke ', 'Tarik tunai dari '],
            self::Deposit => ['Setor tunai ke ', 'Setor tunai dari '],
            self::Transfer => ['Transfer ke ', 'Transfer dari '],
        };
    }

    /** Tentukan jenis dari tipe akun asal dan tujuan. */
    public static function between(?AccountType $from, ?AccountType $to): self
    {
        if ($from === null || $to === null || $from === $to) {
            return self::Transfer;
        }

        return match (true) {
            $to === AccountType::Cash => self::Withdrawal,
            $from === AccountType::Cash => self::Deposit,
            default => self::Transfer,
        };
    }
}

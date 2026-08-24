<?php

namespace App\Enums;

enum DocumentKind: string
{
    /** Berkas tagihan yang diterima (invoice / surat tagihan). */
    case Invoice = 'invoice';

    /** Nota atau bukti pembayaran. */
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Dokumen Tagihan',
            self::Receipt => 'Nota Pembayaran',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

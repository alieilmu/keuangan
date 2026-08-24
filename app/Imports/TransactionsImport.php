<?php

namespace App\Imports;

use App\Imports\Sheets\TransactionsSheetImport;
use App\Models\User;
use App\Services\LedgerService;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import massal transaksi dari file Excel/CSV hasil template sistem.
 *
 * Hanya sheet pertama yang dibaca, sehingga sheet "Panduan" pada template
 * (dan sheet tambahan apa pun milik user) diabaikan tanpa memicu error.
 */
class TransactionsImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    private readonly TransactionsSheetImport $sheet;

    public function __construct(User $user, LedgerService $ledger)
    {
        $this->sheet = new TransactionsSheetImport($user, $ledger);
    }

    /**
     * @return array<int, TransactionsSheetImport>
     */
    public function sheets(): array
    {
        return [0 => $this->sheet];
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        // Sheet lain sengaja dilewati tanpa membatalkan proses import.
    }

    public function importedCount(): int
    {
        return $this->sheet->importedCount();
    }

    /**
     * @return array<int, array{row: int, errors: array<int, string>}>
     */
    public function failures(): array
    {
        return $this->sheet->failures();
    }
}

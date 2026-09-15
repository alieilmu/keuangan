<?php

namespace App\Exports;

use App\Exports\Sheets\ImportReferenceSheet;
use App\Exports\Sheets\TransactionTemplateSheet;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template baku yang diunduh user sebelum melakukan import massal.
 */
class TransactionTemplateExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly User $user) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        // Seluruh akun & kategori grup, sama dengan yang dikenali saat import.
        $accounts = Account::query()
            ->whereIn('user_id', $this->user->visibleUserIds())
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        $categories = Category::query()
            ->whereIn('user_id', $this->user->visibleUserIds())
            ->orderBy('name')
            ->get(['name', 'type'])
            ->unique(fn (Category $c) => $c->type->value.'|'.mb_strtolower($c->name))
            ->values();

        $sampleAccount = $accounts->first() ?? 'Dompet Tunai';
        $sampleExpense = $categories->firstWhere('type.value', 'expense')?->name ?? 'Makanan';
        $sampleIncome = $categories->firstWhere('type.value', 'income')?->name ?? 'Gaji';

        $samples = [
            [now()->startOfMonth()->format('Y-m-d'), 'income', $sampleIncome, $sampleAccount, 7500000, 'Gaji bulanan'],
            [now()->format('Y-m-d'), 'expense', $sampleExpense, $sampleAccount, 45000, 'Makan siang'],
        ];

        $reference = [
            ['tanggal', 'YYYY-MM-DD (atau format tanggal Excel)', 'Wajib diisi'],
            ['tipe', 'income / expense', 'Wajib diisi'],
            ['nominal', 'Angka positif tanpa titik atau koma', 'Wajib diisi'],
            ['keterangan', 'Teks bebas maksimal 255 karakter', 'Opsional'],
            ['akun', $accounts->implode(', ') ?: '(belum ada akun)', 'Wajib, harus sama persis dengan nama akun'],
            ['kategori', $categories->pluck('name')->implode(', ') ?: '(belum ada kategori)', 'Opsional, dibuat otomatis bila belum ada'],
        ];

        return [
            new TransactionTemplateSheet($samples),
            new ImportReferenceSheet($reference),
        ];
    }
}

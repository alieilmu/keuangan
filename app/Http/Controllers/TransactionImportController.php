<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Exports\TransactionsExport;
use App\Exports\TransactionTemplateExport;
use App\Imports\TransactionsImport;
use App\Models\Transaction;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionImportController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Unduh template baku (sheet "Transaksi" + sheet "Panduan").
     */
    public function template(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new TransactionTemplateExport($request->user()),
            'template-import-transaksi.xlsx',
            ExcelFormat::XLSX
        );
    }

    /**
     * Export transaksi (mengikuti filter periode/tipe yang sedang aktif).
     */
    public function export(Request $request): BinaryFileResponse
    {
        $period = DashboardController::resolvePeriod($request->query('period'));

        $query = Transaction::query()
            ->with(['account:id,name', 'category:id,name'])
            ->where('user_id', $request->user()->getKey())
            ->inPeriod($period->year, $period->month)
            ->when(
                in_array($request->query('type'), TransactionType::values(), true),
                fn ($builder) => $builder->where('type', $request->query('type'))
            )
            ->orderBy('transaction_date')
            ->orderBy('id');

        return Excel::download(
            new TransactionsExport($query, 'Transaksi '.$period->format('Y-m')),
            'transaksi-'.$period->format('Y-m').'.xlsx',
            ExcelFormat::XLSX
        );
    }

    /**
     * Import massal. Baris yang gagal divalidasi dilaporkan tanpa membatalkan
     * baris lain yang sudah benar.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $import = new TransactionsImport($request->user(), $this->ledger);

        Excel::import($import, $request->file('file'));

        $failures = $import->failures();
        $imported = $import->importedCount();

        if ($failures !== [] && $imported === 0) {
            return back()
                ->with('error', 'Tidak ada baris yang bisa diimpor. Perbaiki kesalahan berikut lalu unggah ulang.')
                ->with('import_failures', array_slice($failures, 0, 50));
        }

        $message = $imported.' transaksi berhasil diimpor.';

        if ($failures !== []) {
            $message .= ' '.count($failures).' baris dilewati karena tidak valid.';
        }

        return back()
            ->with('success', $message)
            ->with('import_failures', array_slice($failures, 0, 50));
    }
}

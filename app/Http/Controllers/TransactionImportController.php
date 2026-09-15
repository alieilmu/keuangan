<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Exports\TransactionsExport;
use App\Exports\TransactionTemplateExport;
use App\Models\Transaction;
use App\Services\TransactionImportService;
use App\Support\MemberScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionImportController extends Controller
{
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
            // Export mengikuti pemilih tampilan yang sedang aktif di halaman.
            ->whereIn('user_id', MemberScope::resolve(
                $request->user(),
                MemberScope::normalize($request->query('scope'))
            ))
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
    /**
     * Langkah 1: periksa file tanpa menyimpan apa pun. Hasilnya dikirim sebagai
     * flash untuk ditampilkan sebagai tabel pratinjau di popup import.
     */
    public function preview(Request $request, TransactionImportService $importer): RedirectResponse
    {
        $this->validateFile($request);

        $analysis = $importer->analyze($request->user(), $request->file('file'));

        return back()->with('import_preview', $importer->present($analysis));
    }

    /**
     * Langkah 2: simpan baris yang valid. File diperiksa ulang di sini (bukan
     * mengandalkan hasil pratinjau dari browser), jadi yang tersimpan selalu
     * lolos validasi server.
     */
    public function store(Request $request, TransactionImportService $importer): RedirectResponse
    {
        $this->validateFile($request);

        $analysis = $importer->analyze($request->user(), $request->file('file'));

        if ($analysis['file_error'] !== null) {
            return back()->with('error', $analysis['file_error']);
        }

        $result = $importer->commit($request->user(), $analysis, $request->boolean('skip_duplicates', true));

        $skipped = array_filter([
            $result['invalid'] ? $result['invalid'].' baris bermasalah' : null,
            $result['duplicates'] ? $result['duplicates'].' baris duplikat' : null,
        ]);
        $skippedText = $skipped ? ' '.ucfirst(implode(' dan ', $skipped)).' dilewati.' : '';

        if ($result['imported'] === 0) {
            return back()->with('error', 'Tidak ada transaksi yang diimpor.'.$skippedText);
        }

        return back()->with('success', $result['imported'].' transaksi berhasil diimpor.'.$skippedText);
    }

    private function validateFile(Request $request): void
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih file yang akan diimpor.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);
    }
}

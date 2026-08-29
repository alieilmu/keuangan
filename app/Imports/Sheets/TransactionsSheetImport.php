<?php

namespace App\Imports\Sheets;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Pembaca sheet pertama pada file import.
 *
 * Alur amannya:
 *  1. Tiap baris dinormalisasi dulu (tanggal Excel, angka berformat, sinonim tipe).
 *  2. Baris divalidasi; yang gagal dikumpulkan dan dilaporkan, bukan menggagalkan semuanya.
 *  3. Baris valid di-insert massal di dalam satu DB transaction.
 *  4. Saldo seluruh akun dihitung ulang sekali di akhir supaya tetap konsisten.
 */
class TransactionsSheetImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    /** @var array<string, int> nama akun (lowercase) => id */
    private array $accounts;

    /** @var array<string, int> "tipe|nama kategori" (lowercase) => id */
    private array $categories;

    /** @var array<int, array{row: int, errors: array<int, string>}> */
    private array $failures = [];

    private int $imported = 0;

    public function __construct(
        private readonly User $user,
        private readonly LedgerService $ledger,
    ) {
        $this->accounts = Account::query()
            ->where('user_id', $user->getKey())
            ->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name) => [mb_strtolower(trim($name)) => $id])
            ->all();

        $this->categories = Category::query()
            ->where('user_id', $user->getKey())
            ->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Category $category) => [
                $category->type->value.'|'.mb_strtolower(trim($category->name)) => $category->getKey(),
            ])
            ->all();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Normalisasi sebelum validasi supaya format tanggal/angka dari Excel
     * tidak dianggap invalid.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $data, int $index): array
    {
        $data['tanggal'] = self::normalizeDate($data['tanggal'] ?? null);
        $data['tipe'] = self::normalizeType($data['tipe'] ?? null);
        $data['nominal'] = self::normalizeAmount($data['nominal'] ?? null);
        $data['akun'] = is_scalar($data['akun'] ?? null) ? trim((string) $data['akun']) : null;
        $data['kategori'] = is_scalar($data['kategori'] ?? null) ? trim((string) $data['kategori']) : null;
        $data['keterangan'] = is_scalar($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            // Hanya income/expense: kaki transfer tidak boleh masuk lewat import
            // karena harus selalu berpasangan dengan sisi lawannya.
            'tipe' => ['required', 'in:'.implode(',', TransactionType::manualValues())],
            'nominal' => ['required', 'numeric', 'gt:0'],
            'akun' => ['required', 'string', function (string $attribute, mixed $value, callable $fail): void {
                if (! isset($this->accounts[mb_strtolower(trim((string) $value))])) {
                    $fail('Akun "'.$value.'" tidak ditemukan. Buat akunnya dulu atau samakan penulisannya.');
                }
            }],
            'kategori' => ['nullable', 'string', 'max:60'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customValidationMessages(): array
    {
        return [
            'tanggal.required' => 'Kolom tanggal wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak dikenali (gunakan YYYY-MM-DD).',
            'tipe.in' => 'Kolom tipe hanya boleh income atau expense.',
            'nominal.gt' => 'Nominal harus lebih besar dari 0.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
        ];
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        $payload = [];

        DB::transaction(function () use ($rows, $now, &$payload): void {
            foreach ($rows as $row) {
                $type = (string) $row->get('tipe');
                $accountId = $this->accounts[mb_strtolower(trim((string) $row->get('akun')))] ?? null;

                if ($accountId === null) {
                    continue; // Sudah tersaring validasi; jaga-jaga saja.
                }

                $payload[] = [
                    'user_id' => $this->user->getKey(),
                    'account_id' => $accountId,
                    'category_id' => $this->resolveCategoryId($row->get('kategori'), $type),
                    'type' => $type,
                    'amount' => round((float) $row->get('nominal'), 2),
                    'transaction_date' => (string) $row->get('tanggal'),
                    'description' => $row->get('keterangan') ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                Transaction::query()->insert($chunk);
            }
        });

        $this->imported += count($payload);

        // Insert massal melewati LedgerService, jadi saldo dihitung ulang dari histori.
        $this->ledger->recalculate($this->user);
    }

    /**
     * Kategori dibuat otomatis bila belum ada, mengikuti tipe barisnya.
     */
    private function resolveCategoryId(mixed $name, string $type): ?int
    {
        $name = is_scalar($name) ? trim((string) $name) : '';

        if ($name === '') {
            return null;
        }

        $key = $type.'|'.mb_strtolower($name);

        if (isset($this->categories[$key])) {
            return $this->categories[$key];
        }

        $category = Category::query()->create([
            'user_id' => $this->user->getKey(),
            'name' => $name,
            'type' => $type,
            'color' => self::colorFor($name),
        ]);

        return $this->categories[$key] = (int) $category->getKey();
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failures[] = [
                'row' => $failure->row(),
                'errors' => $failure->errors(),
            ];
        }
    }

    public function importedCount(): int
    {
        return $this->imported;
    }

    /**
     * @return array<int, array{row: int, errors: array<int, string>}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Sel bertipe tanggal di Excel datang sebagai angka serial.
        if (is_numeric($value)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        $raw = trim((string) $value);

        foreach (['!Y-m-d' => 'Y-m-d', '!d/m/Y' => 'd/m/Y', '!d-m-Y' => 'd-m-Y', '!Y/m/d' => 'Y/m/d'] as $format => $check) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);

            // createFromFormat bersifat longgar (31 Februari digulung ke Maret),
            // jadi hasilnya diformat ulang dan dibandingkan dengan input asli.
            if ($parsed instanceof \DateTimeImmutable && $parsed->format($check) === $raw) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return CarbonImmutable::parse($raw)->toDateString();
        } catch (\Throwable) {
            // Dikembalikan apa adanya supaya aturan "date" yang melaporkan errornya.
            return $raw;
        }
    }

    private static function normalizeType(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return match ($value) {
            'income', 'pemasukan', 'masuk', 'in', 'debit' => TransactionType::Income->value,
            'expense', 'pengeluaran', 'keluar', 'out', 'kredit' => TransactionType::Expense->value,
            default => $value === '' ? null : $value,
        };
    }

    /**
     * Terima "1.500.000", "1,500,000", "1500000,50" maupun angka biasa.
     */
    private static function normalizeAmount(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $value) ?? '';

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            // Format Indonesia: "1.250.000,75" -> titik ribuan, koma desimal.
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif ($lastDot !== false && $lastComma === false) {
            // Hanya titik: dianggap pemisah ribuan bila muncul lebih dari sekali
            // atau tepat diikuti 3 digit ("1.250.000" / "1.500").
            $thousands = substr_count($clean, '.') > 1 || strlen(substr($clean, $lastDot + 1)) === 3;
            $clean = $thousands ? str_replace('.', '', $clean) : $clean;
        } else {
            // Format Inggris: "1,250,000.75" -> koma ribuan.
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : $value;
    }

    private static function colorFor(string $name): string
    {
        $palette = ['#10b981', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

        return $palette[abs(crc32($name)) % count($palette)];
    }
}

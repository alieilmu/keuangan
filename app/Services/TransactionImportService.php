<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import transaksi dua langkah:
 *
 *  1. analyze() membaca sheet pertama, menormalkan & memvalidasi setiap
 *     baris, lalu mengembalikan hasil pemeriksaan TANPA menyimpan apa pun --
 *     dipakai untuk pratinjau tabel di popup.
 *  2. commit() menyimpan baris yang valid dari hasil analyze() yang sama.
 *
 * Akun dan kategori dicari di seluruh grup (kas bersama), bukan hanya milik
 * user yang mengimpor -- sama seperti form transaksi dan export "Gabungan".
 * Sebelumnya importer hanya mengenali akun milik sendiri, sehingga file hasil
 * export gabungan ditolak "Akun tidak ditemukan" untuk akun milik pasangan.
 */
class TransactionImportService
{
    public const MAX_ROWS = 5000;

    private const REQUIRED_COLUMNS = ['tanggal', 'tipe', 'akun', 'nominal'];

    private const MAX_AMOUNT = 999999999999.99;

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array{file_error: ?string, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function analyze(User $user, UploadedFile $file): array
    {
        try {
            $sheets = Excel::toArray($this->reader($file), $file);
        } catch (\Throwable $e) {
            report($e);

            return $this->fileError('File tidak bisa dibaca. Pastikan formatnya .xlsx, .xls, atau .csv dan tidak rusak.');
        }

        $sheet = array_values(array_filter($sheets[0] ?? [], fn ($row) => ! $this->isEmptyRow($row)));

        if ($sheet === []) {
            return $this->fileError('Sheet pertama tidak berisi baris transaksi. Isi data di bawah baris judul kolom.');
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($sheets[0][0] ?? $sheet[0])));

        if ($missing !== []) {
            return $this->fileError(
                'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Baris pertama sheet pertama harus berisi '
                .'judul kolom sesuai template: tanggal, tipe, kategori, akun, nominal, keterangan.'
            );
        }

        if (count($sheet) > self::MAX_ROWS) {
            return $this->fileError('File berisi '.count($sheet).' baris. Maksimal '.self::MAX_ROWS.' baris per import; pecah menjadi beberapa file.');
        }

        $userId = (int) $user->getKey();
        $scope = $user->visibleUserIds();
        $accounts = $this->indexBy(Account::query()->whereIn('user_id', $scope)->get(['id', 'user_id', 'name']), fn ($a) => mb_strtolower(trim($a->name)));
        $categories = $this->indexBy(
            Category::query()->whereIn('user_id', $scope)->orderBy('id')->get(['id', 'user_id', 'name', 'type']),
            fn ($c) => $c->type->value.'|'.mb_strtolower(trim($c->name))
        );

        $rows = [];

        foreach ($sheets[0] as $index => $raw) {
            if ($this->isEmptyRow($raw)) {
                continue;
            }

            $rows[] = $this->analyzeRow($raw, $index + 2, $userId, $accounts, $categories);
        }

        $this->flagDuplicates($rows, $scope);

        return ['file_error' => null, 'rows' => $rows, 'summary' => $this->summarize($rows, $accounts)];
    }

    /**
     * Simpan baris valid dari hasil analyze(). Kategori yang belum ada di grup
     * dibuat atas nama user yang mengimpor, lalu saldo akun dihitung ulang.
     *
     * @param  array{rows: array<int, array<string, mixed>>}  $analysis
     * @return array{imported: int, invalid: int, duplicates: int}
     */
    public function commit(User $user, array $analysis, bool $skipDuplicates): array
    {
        $rows = $analysis['rows'];
        $valid = array_filter($rows, fn ($r) => $r['errors'] === []);
        $toInsert = array_filter($valid, fn ($r) => ! ($skipDuplicates && $r['duplicate']));

        DB::transaction(function () use ($user, $toInsert): void {
            $now = now();
            $created = [];
            $payload = [];

            foreach ($toInsert as $row) {
                $categoryId = $row['_category_id'];

                if ($categoryId === null && $row['kategori'] !== '') {
                    $key = $row['tipe'].'|'.mb_strtolower($row['kategori']);
                    $categoryId = $created[$key] ??= (int) Category::query()->create([
                        'user_id' => $user->getKey(),
                        'name' => $row['kategori'],
                        'type' => $row['tipe'],
                        'color' => self::colorFor($row['kategori']),
                    ])->getKey();
                }

                $payload[] = [
                    'user_id' => $user->getKey(),
                    'account_id' => $row['_account_id'],
                    'category_id' => $categoryId,
                    'type' => $row['tipe'],
                    'amount' => round((float) $row['nominal'], 2),
                    'transaction_date' => $row['tanggal'],
                    'description' => $row['keterangan'] !== '' ? $row['keterangan'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                Transaction::query()->insert($chunk);
            }
        });

        if ($toInsert !== []) {
            // Insert massal melewati LedgerService, jadi saldo dihitung ulang dari histori.
            $this->ledger->recalculate($user);
        }

        return [
            'imported' => count($toInsert),
            'invalid' => count($rows) - count($valid),
            'duplicates' => count($valid) - count($toInsert),
        ];
    }

    /**
     * Bentuk yang dikirim ke antarmuka: kunci internal (_account_id dst.) dibuang.
     *
     * @param  array{file_error: ?string, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}  $analysis
     * @return array<string, mixed>
     */
    public function present(array $analysis): array
    {
        return [
            'summary' => $analysis['summary'],
            'rows' => array_map(
                fn ($row) => array_filter($row, fn ($key) => ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY),
                $analysis['rows']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, array<int, mixed>>  $accounts
     * @param  array<string, array<int, mixed>>  $categories
     * @return array<string, mixed>
     */
    private function analyzeRow(array $raw, int $rowNumber, int $userId, array $accounts, array $categories): array
    {
        $text = fn (string $key) => is_scalar($raw[$key] ?? null) ? trim((string) $raw[$key]) : '';
        $errors = [];

        $date = self::normalizeDate($raw['tanggal'] ?? null);

        if ($date === null) {
            $errors[] = 'Tanggal wajib diisi.';
        } elseif (! self::isValidDate($date)) {
            $errors[] = 'Tanggal "'.$text('tanggal').'" tidak dikenali. Gunakan format YYYY-MM-DD.';
        }

        $type = self::normalizeType($raw['tipe'] ?? null);

        if ($type === null) {
            $errors[] = 'Tipe wajib diisi.';
        } elseif (! in_array($type, TransactionType::manualValues(), true)) {
            $errors[] = 'Tipe "'.$text('tipe').'" tidak dikenal. Gunakan pemasukan atau pengeluaran.';
        }

        $amount = self::normalizeAmount($raw['nominal'] ?? null);

        if ($amount === null) {
            $errors[] = 'Nominal wajib diisi.';
        } elseif (! is_float($amount)) {
            $errors[] = 'Nominal "'.$text('nominal').'" bukan angka.';
        } elseif ($amount <= 0) {
            $errors[] = 'Nominal harus lebih besar dari 0.';
        } elseif ($amount > self::MAX_AMOUNT) {
            $errors[] = 'Nominal terlalu besar.';
        }

        $accountName = $text('akun');
        $accountId = null;

        if ($accountName === '') {
            $errors[] = 'Akun wajib diisi.';
        } else {
            [$accountId, $accountError] = $this->resolveAccount($accounts[mb_strtolower($accountName)] ?? [], $accountName, $userId);

            if ($accountError !== null) {
                $errors[] = $accountError;
            }
        }

        $categoryName = $text('kategori');
        $categoryId = null;

        if (mb_strlen($categoryName) > 60) {
            $errors[] = 'Nama kategori maksimal 60 karakter.';
        } elseif ($categoryName !== '' && is_string($type)) {
            $matches = $categories[$type.'|'.mb_strtolower($categoryName)] ?? [];
            $own = array_values(array_filter($matches, fn ($c) => (int) $c->user_id === $userId));
            $categoryId = ($own[0] ?? $matches[0] ?? null)?->id;
        }

        $description = $text('keterangan');

        if (mb_strlen($description) > 255) {
            $errors[] = 'Keterangan maksimal 255 karakter.';
        }

        return [
            'row' => $rowNumber,
            'tanggal' => $date !== null && self::isValidDate($date) ? $date : $text('tanggal'),
            'tipe' => $type ?? '',
            'tipe_label' => match ($type) {
                TransactionType::Income->value => 'Pemasukan',
                TransactionType::Expense->value => 'Pengeluaran',
                default => $text('tipe'),
            },
            'kategori' => $categoryName,
            'akun' => $accountName,
            'nominal' => is_float($amount) ? $amount : null,
            'nominal_raw' => $text('nominal'),
            'keterangan' => $description,
            'errors' => $errors,
            'duplicate' => false,
            'new_category' => $errors === [] && $categoryName !== '' && $categoryId === null,
            '_account_id' => $accountId,
            '_category_id' => $categoryId,
        ];
    }

    /**
     * Akun milik sendiri didahulukan; akun anggota lain dipakai bila namanya
     * unik di grup. Nama yang dipakai beberapa akun anggota lain dilaporkan,
     * bukan ditebak.
     *
     * @param  array<int, Account>  $matches
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveAccount(array $matches, string $name, int $userId): array
    {
        if ($matches === []) {
            return [null, 'Akun "'.$name.'" tidak ditemukan di grup Anda.'];
        }

        $own = array_values(array_filter($matches, fn ($a) => (int) $a->user_id === $userId));

        if (count($own) === 1) {
            return [$own[0]->id, null];
        }

        if (count($matches) === 1) {
            return [$matches[0]->id, null];
        }

        return [null, 'Nama akun "'.$name.'" dipakai lebih dari satu akun di grup Anda. Ubah salah satu namanya.'];
    }

    /**
     * Tandai baris valid yang persis sama dengan transaksi yang sudah ada
     * (tanggal, tipe, nominal, akun, keterangan) -- biasanya akibat file
     * export yang diimpor ulang.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $scope
     */
    private function flagDuplicates(array &$rows, array $scope): void
    {
        $dates = array_column(array_filter($rows, fn ($r) => $r['errors'] === []), 'tanggal');

        if ($dates === []) {
            return;
        }

        $existing = Transaction::query()
            ->whereIn('user_id', $scope)
            ->whereBetween('transaction_date', [min($dates), max($dates)])
            ->whereIn('type', TransactionType::manualValues())
            ->get(['account_id', 'type', 'amount', 'transaction_date', 'description'])
            ->mapWithKeys(fn (Transaction $t) => [self::duplicateKey(
                $t->transaction_date->format('Y-m-d'), $t->type->value, (float) $t->amount, (int) $t->account_id, (string) $t->description
            ) => true])
            ->all();

        foreach ($rows as &$row) {
            if ($row['errors'] === []) {
                $row['duplicate'] = isset($existing[self::duplicateKey(
                    $row['tanggal'], $row['tipe'], (float) $row['nominal'], (int) $row['_account_id'], $row['keterangan']
                )]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<int, Account>>  $accounts
     * @return array<string, mixed>
     */
    private function summarize(array $rows, array $accounts): array
    {
        $groups = [];

        foreach ($rows as $row) {
            foreach ($row['errors'] as $message) {
                $groups[$message] ??= ['message' => $message, 'count' => 0, 'rows' => []];
                $groups[$message]['count']++;

                if (count($groups[$message]['rows']) < 12) {
                    $groups[$message]['rows'][] = $row['row'];
                }
            }
        }

        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);
        $invalid = count(array_filter($rows, fn ($r) => $r['errors'] !== []));
        $accountErrors = count(array_filter($groups, fn ($g) => str_starts_with($g['message'], 'Akun "')));

        return [
            'file_error' => null,
            'total' => count($rows),
            'valid' => count($rows) - $invalid,
            'invalid' => $invalid,
            'duplicates' => count(array_filter($rows, fn ($r) => $r['duplicate'])),
            'error_groups' => array_values($groups),
            'new_categories' => array_values(array_unique(array_column(array_filter($rows, fn ($r) => $r['new_category']), 'kategori'))),
            // Petunjuk nama akun yang sah, hanya bila ada akun yang tidak dikenali.
            'accounts_hint' => $accountErrors > 0
                ? array_values(array_unique(array_map(fn ($list) => $list[0]->name, $accounts)))
                : [],
        ];
    }

    /** @return array{file_error: string, rows: array<int, mixed>, summary: array<string, mixed>} */
    private function fileError(string $message): array
    {
        return [
            'file_error' => $message,
            'rows' => [],
            'summary' => [
                'file_error' => $message, 'total' => 0, 'valid' => 0, 'invalid' => 0, 'duplicates' => 0,
                'error_groups' => [], 'new_categories' => [], 'accounts_hint' => [],
            ],
        ];
    }

    /**
     * Pembaca sheet dengan baris judul. Untuk CSV, pemisah dideteksi dari
     * baris pertama: Excel berbahasa Indonesia menyimpan CSV dengan titik koma.
     */
    private function reader(UploadedFile $file): Import
    {
        $csv = in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true);
        $delimiter = ',';

        if ($csv) {
            $handle = fopen($file->getRealPath(), 'r');
            $first = (string) fgets($handle);
            fclose($handle);
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        }

        return new class($delimiter, $csv) extends DefaultValueBinder implements Import, WithCustomCsvSettings, WithCustomValueBinder, WithHeadingRow
        {
            public function __construct(private readonly string $delimiter, private readonly bool $csv) {}

            public function getCsvSettings(): array
            {
                return ['delimiter' => $this->delimiter];
            }

            /**
             * Isi CSV dibaca apa adanya sebagai teks. Tanpa ini PhpSpreadsheet
             * sudah mengubah "1.500" (seribu lima ratus) menjadi angka 1,5
             * sebelum sempat dinormalkan.
             */
            public function bindValue(Cell $cell, mixed $value): bool
            {
                if ($this->csv) {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

                    return true;
                }

                return parent::bindValue($cell, $value);
            }
        };
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<string, array<int, T>>
     */
    private function indexBy(iterable $items, callable $key): array
    {
        $index = [];

        foreach ($items as $item) {
            $index[$key($item)][] = $item;
        }

        return $index;
    }

    /** @param  array<string, mixed>  $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function duplicateKey(string $date, string $type, float $amount, int $accountId, string $description): string
    {
        return implode('|', [$date, $type, number_format($amount, 2, '.', ''), $accountId, mb_strtolower(trim($description))]);
    }

    private static function isValidDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
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
     * Mengembalikan float bila berhasil, teks asli bila tidak, null bila kosong.
     */
    private static function normalizeAmount(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
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

        return is_numeric($clean) ? (float) $clean : (string) $value;
    }

    private static function colorFor(string $name): string
    {
        $palette = ['#10b981', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

        return $palette[abs(crc32($name)) % count($palette)];
    }
}

<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;

/**
 * Panduan interaktif untuk pengguna baru.
 *
 * Setiap langkah punya SYARAT SELESAI yang diperiksa dari data nyata milik
 * user, bukan sekadar tombol "lanjut". Jadi panduan ini benar-benar
 * memastikan pengguna mencoba fiturnya -- kalau ia menutup panduan lalu
 * kembali besok, kemajuannya tetap terbaca dari data yang sudah ia buat.
 *
 * Langkah 1 (pengenalan dashboard) dan langkah terakhir (penutup) bersifat
 * informasional sehingga tidak punya syarat data.
 */
class WalkthroughService
{
    public const TOTAL_STEPS = 6;

    /** Nominal contoh untuk langkah kredit, disebut di instruksi. */
    public const CREDIT_EXAMPLE_TOTAL = 12000000;

    public const CREDIT_EXAMPLE_INSTALLMENT = 1300000;

    /**
     * Definisi seluruh langkah.
     *
     * @return array<int, array<string, mixed>>
     */
    public function steps(): array
    {
        return [
            1 => [
                'key' => 'dashboard',
                'title' => 'Selamat datang di Dashboard',
                'body' => 'Di sini Anda melihat ringkasan arus kas: total saldo semua akun, '
                    .'pemasukan & pengeluaran bulan ini, alokasi pengeluaran per kategori, '
                    .'tagihan yang akan jatuh tempo, serta progres anggaran dan kredit. '
                    .'Angkanya masih kosong karena belum ada data -- mari kita isi bersama.',
                'route' => '/dashboard',
                'cta' => 'Mulai Panduan',
                'auto' => false,
            ],
            2 => [
                'key' => 'account',
                'title' => 'Langkah 1: Buat Akun Dana',
                'body' => 'Akun dana adalah tempat uang Anda berada -- dompet tunai, rekening bank, '
                    .'atau e-wallet. Buat satu akun dan isi Saldo Awal sesuai uang yang Anda '
                    .'punya sekarang, supaya perhitungan saldo akurat sejak awal.',
                'hint' => 'Klik "+ Akun", isi nama dan Saldo Awal lebih dari 0, lalu simpan.',
                'route' => '/accounts',
                'cta' => 'Buka Halaman Akun',
                'auto' => true,
            ],
            3 => [
                'key' => 'transaction',
                'title' => 'Langkah 2: Catat Transaksi Pengeluaran',
                'body' => 'Sekarang coba catat satu pembelian -- misalnya belanja atau makan. '
                    .'Pilih jenis Pengeluaran, tentukan akun dan kategorinya, lalu isi nominalnya. '
                    .'Saldo akun akan otomatis berkurang.',
                'hint' => 'Klik "+ Catat", pilih jenis Pengeluaran, lalu simpan.',
                'route' => '/transactions',
                'cta' => 'Buka Halaman Transaksi',
                'auto' => true,
            ],
            4 => [
                'key' => 'credit',
                'title' => 'Langkah 3: Catat Kredit / Cicilan',
                'body' => 'Punya cicilan jangka panjang? Catat di sini agar tagihan angsurannya '
                    .'dibuat otomatis setiap bulan. Contohnya: Cicilan HP total Rp12.000.000 '
                    .'dengan angsuran Rp1.300.000 per bulan.',
                'hint' => 'Klik "+ Kredit", isi nama "Cicilan HP", total 12.000.000, dan angsuran 1.300.000.',
                'route' => '/credits',
                'cta' => 'Buka Halaman Kredit',
                'auto' => true,
            ],
            5 => [
                'key' => 'bill',
                'title' => 'Langkah 4: Bayar Tagihan',
                'body' => 'Kredit yang tadi Anda buat menghasilkan tagihan angsuran. Bayar satu '
                    .'tagihan untuk melihat alurnya: saldo akun berkurang dan transaksinya '
                    .'tercatat otomatis. Anda boleh mengunggah bukti pembayaran, atau langsung '
                    .'klik Bayar tanpa melampirkan apa pun.',
                'hint' => 'Pada salah satu tagihan, klik "Bayar", pilih akun sumber, lalu konfirmasi.',
                'route' => '/bills',
                'cta' => 'Buka Halaman Tagihan',
                'auto' => true,
            ],
            6 => [
                'key' => 'finish',
                'title' => 'Panduan Selesai',
                'body' => 'Anda sudah mencoba alur utama aplikasi: akun dana, transaksi, kredit, '
                    .'dan pembayaran tagihan. Sekarang pilih -- simpan data yang tadi Anda buat, '
                    .'atau kosongkan kembali supaya Anda mulai dari nol dengan data sungguhan.',
                'route' => '/dashboard',
                'auto' => false,
            ],
        ];
    }

    /**
     * Apakah syarat data langkah tertentu sudah terpenuhi.
     * Langkah informasional selalu dianggap "siap dilanjutkan".
     */
    public function isStepSatisfied(User $user, int $step): bool
    {
        return match ($step) {
            2 => $user->accounts()->where('opening_balance', '>', 0)->exists(),
            3 => $user->transactions()->where('type', TransactionType::Expense->value)->exists(),
            4 => $user->credits()->exists(),
            5 => $user->bills()->where('status', 'paid')->exists(),
            default => true,
        };
    }

    /**
     * Bentuk data panduan untuk dikirim ke antarmuka. Null bila user tidak
     * sedang menjalani panduan.
     *
     * @return array<string, mixed>|null
     */
    public function present(?User $user): ?array
    {
        if ($user === null || $user->walkthrough_step === null) {
            return null;
        }

        $step = (int) $user->walkthrough_step;
        $steps = $this->steps();

        if (! isset($steps[$step])) {
            return null;
        }

        return array_merge($steps[$step], [
            'step' => $step,
            'total' => self::TOTAL_STEPS,
            'satisfied' => $this->isStepSatisfied($user, $step),
            'is_final' => $step === self::TOTAL_STEPS,
        ]);
    }
}

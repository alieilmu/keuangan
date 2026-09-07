<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mengosongkan seluruh data keuangan milik SATU user, lalu menyiapkan
 * kembali akun & kategori bawaan seperti akun yang baru dibuat.
 *
 * Dipakai pada akhir panduan interaktif, ketika pengguna memilih membuang
 * data latihan dan mulai dari nol.
 *
 * Cakupannya sengaja dibatasi pada baris milik user itu sendiri (user_id),
 * BUKAN seluruh anggota grup -- supaya seorang anggota keluarga tidak bisa
 * menghapus catatan pasangannya lewat tombol ini.
 */
class UserDataResetService
{
    public function __construct(private readonly DefaultDataProvisioner $provisioner) {}

    public function reset(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $userId = $user->getKey();

            // Dokumen dihapus lebih dulu supaya berkas fisiknya ikut
            // dibersihkan lewat service-nya, bukan sekadar baris database.
            $documents = app(DocumentService::class);

            foreach (\App\Models\Document::query()->where('user_id', $userId)->get() as $document) {
                $documents->delete($document);
            }

            // Urutan mengikuti ketergantungan foreign key: transaksi lebih
            // dulu (menunjuk ke transfer, akun, kategori), baru induknya.
            $user->transactions()->delete();
            DB::table('transfers')->where('user_id', $userId)->delete();
            $user->bills()->delete();
            $user->credits()->delete();
            $user->budgets()->delete();
            $user->savingsGoals()->delete();
            $user->accounts()->delete();
            $user->categories()->delete();

            // Siapkan lagi akun & kategori bawaan agar user langsung bisa
            // mencatat, persis kondisi setelah mendaftar.
            $this->provisioner->provision($user);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group = satu kas bersama. Seluruh user di dalam satu group melihat dan
     * mengelola kumpulan data keuangan yang sama (akun, transaksi, tagihan,
     * anggaran, tabungan, kredit).
     *
     * Kepemilikan baris lama TIDAK diubah: kolom user_id pada setiap tabel
     * tetap mencatat siapa yang menginput, sehingga riwayat tetap bisa
     * ditelusuri per orang. Yang berubah hanya cakupan siapa yang boleh
     * melihatnya, yaitu semua anggota group yang sama.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};

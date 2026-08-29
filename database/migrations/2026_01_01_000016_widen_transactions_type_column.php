<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom transactions.type dibuat varchar(10) ketika hanya ada dua nilai:
     * "income" (6) dan "expense" (7). Modul Transfer Dana menambahkan
     * "transfer_out" (12) dan "transfer_in" (11) lewat App\Enums\TransactionType,
     * tetapi lebar kolomnya tidak ikut disesuaikan. Akibatnya setiap transfer
     * gagal dengan HTTP 500:
     *
     *   SQLSTATE[22001]: String data, right truncated: 1406
     *   Data too long for column 'type' at row 1
     *
     * Dilebarkan ke 20 agar konsisten dengan accounts.type yang sudah varchar(20)
     * dan menyisakan ruang untuk tipe baru.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });
    }

    public function down(): void
    {
        // Hanya aman bila tidak ada lagi baris kaki transfer yang tersimpan,
        // karena nilainya melebihi 10 karakter.
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 10)->change();
        });
    }
};

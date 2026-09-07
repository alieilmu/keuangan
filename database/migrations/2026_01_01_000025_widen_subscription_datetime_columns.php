<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tanggal langganan dibuat bertipe TIMESTAMP, yang di MySQL hanya
     * mampu menyimpan sampai 19 Januari 2038. Akibatnya admin yang menetapkan
     * masa aktif jauh ke depan langsung mendapat error dan perubahannya gagal
     * tersimpan:
     *
     *   SQLSTATE[22007]: Invalid datetime format: 1292
     *   Incorrect datetime value: '2714-10-07 00:00:00' for column 'expires_at'
     *
     * Diubah ke DATETIME yang jangkauannya sampai tahun 9999. Nilai yang sudah
     * ada ikut terbawa apa adanya karena kedua tipe memakai format sama.
     *
     * created_at/updated_at sengaja dibiarkan TIMESTAMP: keduanya selalu diisi
     * waktu kejadian, tidak pernah tanggal masa depan.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('started_at')->change();
            $table->dateTime('expires_at')->nullable()->change();
            $table->dateTime('canceled_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('started_at')->change();
            $table->timestamp('expires_at')->nullable()->change();
            $table->timestamp('canceled_at')->nullable()->change();
        });
    }
};

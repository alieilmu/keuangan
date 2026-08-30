<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua kolom baru untuk panel admin SaaS:
     * - is_admin  : akses ke /admin, terpisah dari sistem role/permission
     *               penuh karena baru dibutuhkan untuk satu peran.
     * - is_blocked/blocked_at : status akun yang bisa diaktifkan admin.
     *   Ditegakkan di dua titik: saat login (AuthenticatedSessionController)
     *   dan pada request berikutnya (middleware EnsureNotBlocked), sehingga
     *   sesi yang sedang berjalan pun ikut diputus saat diblokir.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('group_id');
            $table->boolean('is_blocked')->default(false)->after('is_admin');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_blocked', 'blocked_at']);
        });
    }
};

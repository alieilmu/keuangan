<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status panduan interaktif (walkthrough) untuk pengguna baru.
     *
     * walkthrough_step menyimpan nomor langkah yang SEDANG dikerjakan
     * (1..N). Null berarti pengguna tidak sedang menjalani panduan --
     * baik karena sudah selesai, dilewati, maupun akun lama yang dibuat
     * sebelum fitur ini ada.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('walkthrough_step')->nullable()->after('blocked_at');
            $table->timestamp('walkthrough_completed_at')->nullable()->after('walkthrough_step');
            $table->timestamp('walkthrough_skipped_at')->nullable()->after('walkthrough_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['walkthrough_step', 'walkthrough_completed_at', 'walkthrough_skipped_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Wajib untuk akun bertipe bank & e-wallet, dikosongkan untuk kas fisik.
            // Sengaja TIDAK unik: satu rekening bisa dicatat lebih dari sekali
            // (mis. rekening bersama) dan transfer sesama nomor tetap diizinkan.
            $table->string('account_number', 40)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }
};

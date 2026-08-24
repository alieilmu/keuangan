<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Tagihan yang dibuat otomatis oleh modul kredit.
            $table->foreignId('credit_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable()->after('credit_id');

            // Kunci idempotensi: satu kredit hanya boleh punya satu tagihan per
            // nomor angsuran, jadi generator harian aman dijalankan berulang.
            $table->unique(['credit_id', 'installment_number'], 'bills_credit_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_credit_installment_unique');
            $table->dropConstrainedForeignId('credit_id');
            $table->dropColumn('installment_number');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Setiap transfer menghasilkan dua baris transaksi (keluar & masuk)
            // yang keduanya menunjuk ke baris transfer yang sama.
            $table->foreignId('transfer_id')->nullable()->after('category_id')
                ->constrained()->cascadeOnDelete();

            $table->index(['transfer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_id');
        });
    }
};

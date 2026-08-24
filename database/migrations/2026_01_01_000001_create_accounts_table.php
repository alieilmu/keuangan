<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('cash'); // cash | bank | ewallet
            // Saldo awal saat akun dibuat, dipisah dari saldo berjalan supaya
            // saldo bisa dihitung ulang: balance = opening_balance + net transaksi.
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('color', 9)->default('#10b981');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

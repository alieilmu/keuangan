<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Kategori sengaja nullable + nullOnDelete supaya histori arus kas
            // tidak ikut terhapus ketika sebuah kategori dihapus user.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // income | expense
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();

            // Index utama untuk laporan bulanan & agregasi anggaran.
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type', 'transaction_date']);
            $table->index(['user_id', 'category_id', 'transaction_date']);
            $table->index(['account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

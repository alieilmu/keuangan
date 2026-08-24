<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Akun sumber pembayaran (opsional saat tagihan dibuat).
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            // Kategori pengeluaran yang dipakai saat tagihan dibayar.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // Transaksi hasil pembayaran, agar aksi "Bayar" bisa dibatalkan.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('status', 10)->default('unpaid'); // unpaid | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('notes')->nullable();
            // Jarak hari sebelum jatuh tempo untuk mulai mengirim pengingat.
            $table->unsignedTinyInteger('remind_days_before')->default(3);
            $table->date('reminded_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'due_date']);
            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};

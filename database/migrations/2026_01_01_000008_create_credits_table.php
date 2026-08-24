<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Sumber dana & kategori default yang dipakai saat tagihan cicilan dibayar.
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->decimal('total_amount', 15, 2);          // total pokok pinjaman
            $table->decimal('interest_rate', 5, 2)->nullable(); // bunga % per tahun, opsional
            $table->decimal('monthly_installment', 15, 2);   // cicilan per bulan
            $table->date('start_date');
            $table->date('end_date');                        // target selesai

            // Total tenor diturunkan dari start_date..end_date dan disimpan agar
            // progress "bulan ke-X dari Y" tidak perlu dihitung ulang tiap query.
            $table->unsignedSmallInteger('tenor_months');
            $table->unsignedSmallInteger('remaining_months'); // sisa tenor
            $table->unsignedTinyInteger('due_day');           // tanggal jatuh tempo tiap bulan

            $table->string('status', 12)->default('active');  // active | paid_off | closed
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};

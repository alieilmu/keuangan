<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->string('description')->nullable();
            // Nomor referensi / bukti dari bank, opsional.
            $table->string('reference', 60)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transfer_date']);
            $table->index(['from_account_id', 'transfer_date']);
            $table->index(['to_account_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};

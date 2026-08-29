<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Tagihan setoran tabungan yang dibuat otomatis tiap bulan.
            $table->foreignId('savings_goal_id')->nullable()->after('credit_id')
                ->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('contribution_number')->nullable()->after('installment_number');
            // Transfer hasil pembayaran setoran, dipakai saat pembayaran dibatalkan.
            $table->foreignId('transfer_id')->nullable()->after('transaction_id')
                ->constrained()->nullOnDelete();

            $table->unique(['savings_goal_id', 'contribution_number'], 'bills_savings_contribution_unique');
        });

        Schema::table('transfers', function (Blueprint $table) {
            // Transfer hasil pembayaran setoran, dipakai menghitung dana terkumpul.
            $table->foreignId('savings_goal_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            $table->index(['savings_goal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('savings_goal_id');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_savings_contribution_unique');
            $table->dropConstrainedForeignId('transfer_id');
            $table->dropConstrainedForeignId('savings_goal_id');
            $table->dropColumn('contribution_number');
        });
    }
};

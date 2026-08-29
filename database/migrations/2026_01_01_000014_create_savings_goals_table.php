<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Alokasi akun ganda:
            //  - source  : akun sumber dana, tempat uang diambil tiap bulan
            //  - storage : akun penyimpanan, tempat dana tabungan dikumpulkan
            $table->foreignId('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('storage_account_id')->constrained('accounts')->cascadeOnDelete();

            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('monthly_contribution', 15, 2);
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('due_day'); // tanggal setoran tiap bulan
            $table->string('status', 12)->default('active'); // active | completed | paused
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};

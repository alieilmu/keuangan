<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('limit_amount', 15, 2);
            $table->unsignedTinyInteger('period_month'); // 1 - 12
            $table->unsignedSmallInteger('period_year');
            // Menyimpan ambang batas terakhir yang sudah dinotifikasikan (70 / 100)
            // supaya scheduler tidak mengirim push berulang tiap hari.
            $table->unsignedTinyInteger('notified_threshold')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'period_year', 'period_month'], 'budgets_unique_period');
            $table->index(['user_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog paket. Tersimpan sebagai baris data (bukan enum) supaya harga
     * dan fitur bisa diubah admin tanpa migrasi/deploy ulang.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // 'free' | 'premium'
            $table->string('name', 60);
            $table->unsignedInteger('price')->default(0); // rupiah / bulan
            $table->unsignedInteger('max_members')->nullable(); // null = tanpa batas
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

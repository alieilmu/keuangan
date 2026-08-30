<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat percobaan pembayaran QRIS Doku SIMULASI. Tidak ada koneksi ke
     * gateway sungguhan -- baris ini dibuat murni oleh alur demo admin untuk
     * memperlihatkan bagaimana status pembayaran akan tercatat di produksi.
     */
    public function up(): void
    {
        Schema::create('payment_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20)->default('qris_doku');
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending'); // pending|success|failed
            $table->string('reference', 40)->unique();
            $table->timestamp('simulated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_simulations');
    }
};

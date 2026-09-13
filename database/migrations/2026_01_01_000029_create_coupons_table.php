<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode diskon. Jumlah pemakaian TIDAK disimpan sebagai counter, tetapi
     * dihitung dari pesanan yang memakainya (status pending/paid) -- counter
     * terpisah mudah melenceng saat pesanan dibatalkan.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('description', 120)->nullable();
            $table->string('type', 10);                 // percent|fixed
            $table->unsignedInteger('value');           // persen (1-100) atau rupiah
            $table->unsignedInteger('max_uses')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

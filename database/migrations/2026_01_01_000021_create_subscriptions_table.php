<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Langganan melekat pada Group (kas bersama = tenant), bukan pada User
     * perorangan. Satu tenant bisa dianggap satu pelanggan yang membayar;
     * seluruh anggota grup mewarisi status langganan yang sama, sehingga
     * tidak ada kejanggalan dua anggota satu kas berbeda paket.
     *
     * Satu grup hanya boleh punya satu baris subscription aktif pada satu
     * waktu (unique pada group_id) -- riwayat upgrade/downgrade dicatat
     * lewat perubahan kolom, bukan baris baru, agar query status "langganan
     * saat ini" selalu sederhana: satu join, tanpa perlu ->latest().
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active'); // active|expired|canceled
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable(); // null = paket Free, tanpa kedaluwarsa
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

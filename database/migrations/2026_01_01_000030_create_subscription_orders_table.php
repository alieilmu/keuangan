<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesanan langganan yang dibuat pengguna: beli/perpanjang paket, atau
     * tambah slot anggota. Pembayaran masih simulasi QRIS -- pesanan baru
     * diterapkan ke langganan setelah admin mengonfirmasinya.
     *
     * Rincian harga & kode diskon disimpan sebagai snapshot, supaya
     * perubahan harga paket atau kupon di kemudian hari tidak mengubah
     * nilai pesanan yang sudah dibuat.
     */
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                       // plan|add_member
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('billing_months')->nullable();
            $table->unsignedSmallInteger('extra_members')->default(0);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 30)->nullable();
            $table->json('lines')->nullable();
            $table->string('status', 20)->default('pending'); // pending|paid|canceled
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paket berdurasi: satu paket langganan (Basic, Premium, ...) kini bisa
     * dijual dalam beberapa durasi -- 1, 3, 6, 12 bulan -- masing-masing
     * dengan harganya sendiri yang diatur admin.
     *
     * subscription_plans.price tetap ada sebagai harga dasar per bulan.
     * Supaya paket yang sudah ada langsung bisa dibeli, setiap paket
     * berbayar mendapat satu opsi durasi 1 bulan seharga price-nya saat
     * ini. Ini hanya MENAMBAH baris ke tabel baru; baris paket yang ada
     * tidak diubah.
     *
     * extra_member_price: harga per slot anggota tambahan per bulan, untuk
     * paket yang punya batas kuota. Default 0 = slot tambahan tidak dijual.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('extra_member_price')->default(0)->after('max_members');
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('months');
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'months']);
        });

        $now = now();

        DB::table('subscription_plans')
            ->where('price', '>', 0)
            ->where('code', '!=', 'demo')
            ->get(['id', 'price'])
            ->each(fn ($plan) => DB::table('plan_prices')->insert([
                'subscription_plan_id' => $plan->id,
                'months' => 1,
                'price' => $plan->price,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('extra_member_price');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - billing_months : durasi periode yang dibeli (1/3/6/12 ...).
     * - amount         : nilai langganan per periode itu (setelah diskon),
     *                    termasuk slot anggota tambahan. Null untuk baris
     *                    lama -- MRR lalu jatuh ke harga dasar paket, jadi
     *                    angka analitik untuk data lama tidak berubah.
     * - extra_members  : slot anggota yang dibeli di atas kuota paket.
     *
     * Semua ber-default, sehingga baris langganan yang ada tetap bernilai
     * sama persis dan perilakunya tidak berubah.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_months')->default(1)->after('expires_at');
            $table->unsignedInteger('amount')->nullable()->after('billing_months');
            $table->unsignedSmallInteger('extra_members')->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_months', 'amount', 'extra_members']);
        });
    }
};

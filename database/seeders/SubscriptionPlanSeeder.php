<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /** @var array<int, array<string, mixed>> */
    private const PLANS = [
        [
            'code' => 'free',
            'name' => 'Free',
            'price' => 0,
            'max_members' => 2,
            'features' => ['Transaksi & anggaran dasar', 'Maks. 2 anggota per tenant', 'Tanpa Web Push'],
        ],
        [
            'code' => 'premium',
            'name' => 'Premium',
            'price' => 49000,
            'max_members' => null,
            'features' => ['Anggota tanpa batas', 'Import/export Excel', 'Notifikasi Web Push', 'Kredit & Tabungan Terencana'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }

        $this->command?->info('Katalog paket (Free/Premium) siap.');
    }
}

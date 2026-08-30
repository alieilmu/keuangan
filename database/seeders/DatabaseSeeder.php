<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Models\Group;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DefaultDataProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Dua akun login rumah tangga. Masing-masing punya data terpisah:
     * akun, kategori, anggaran, tagihan, dan transaksi tidak saling terlihat.
     *
     * @var array<int, array{name: string, email: string}>
     */
    private const USERS = [
        ['name' => 'Suami', 'email' => 'suami@keuangan.test'],
        ['name' => 'Istri', 'email' => 'istri@keuangan.test'],
    ];

    private const PASSWORD = '241025';

    public function run(DefaultDataProvisioner $provisioner): void
    {
        foreach (self::USERS as $attributes) {
            $user = User::query()->firstOrCreate(
                ['email' => $attributes['email']],
                ['name' => $attributes['name'], 'password' => Hash::make(self::PASSWORD)]
            );

            // Akun (Dompet Tunai, Rekening Bank, e-Wallet) bersaldo 0 dan
            // kategori bawaan disiapkan; data transaksi sengaja dikosongkan.
            $provisioner->provision($user);

            $this->command?->info("User {$attributes['name']} siap: {$attributes['email']}");
        }

        $this->call([SubscriptionPlanSeeder::class, AdminSeeder::class]);

        // Setiap tenant (Group) tanpa baris subscription didaftarkan ke
        // paket Free -- default paling aman, admin bisa upgrade lewat panel.
        $freePlan = SubscriptionPlan::query()->where('code', 'free')->first();

        if ($freePlan) {
            Group::query()->doesntHave('subscription')->get()->each(
                fn (Group $group) => $group->subscription()->create([
                    'subscription_plan_id' => $freePlan->id,
                    'status' => SubscriptionStatus::Active->value,
                    'started_at' => now(),
                    'expires_at' => null,
                ])
            );
        }
    }
}

<?php

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrasi DATA (bukan skema): memastikan ketentuan baru berlaku juga
     * untuk akun yang sudah terlanjur dibuat sebelum aturan ini ada.
     *
     * Sebelumnya pendaftaran mandiri tidak menaruh user ke grup mana pun
     * dan tidak membuat baris subscription sama sekali -- akibatnya ada
     * akun yang memakai aplikasi tanpa batas waktu dan tanpa status
     * langganan. Migrasi ini menutup celah itu:
     *
     *   1. Membuat paket "Demo" bila belum ada.
     *   2. Setiap user NON-ADMIN yang belum punya grup diberi grup sendiri
     *      (satu tenant per orang) + langganan Demo.
     *   3. Setiap grup yang belum punya baris subscription juga diberi Demo.
     *
     * Masa aktif dihitung 7 hari sejak akun DIBUAT, bukan sejak migrasi
     * dijalankan, supaya akun yang sudah lama terdaftar tidak mendadak
     * mendapat perpanjangan gratis.
     */
    public function up(): void
    {
        $now = now();

        $demoId = DB::table('subscription_plans')->where('code', 'demo')->value('id');

        if ($demoId === null) {
            $demoId = DB::table('subscription_plans')->insertGetId([
                'code' => 'demo',
                'name' => 'Demo',
                'price' => 0,
                'max_members' => 2,
                'features' => json_encode([
                    'Masa coba 7 hari',
                    'Seluruh fitur pencatatan terbuka',
                    'Panduan interaktif untuk pengguna baru',
                ]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 1. User non-admin tanpa grup -> buatkan grup sendiri.
        $orphans = DB::table('users')
            ->whereNull('group_id')
            ->where('is_admin', false)
            ->get(['id', 'name', 'created_at']);

        foreach ($orphans as $user) {
            $groupId = DB::table('groups')->insertGetId([
                'name' => 'Kas '.$user->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $user->id)->update(['group_id' => $groupId]);
        }

        // 2. Grup tanpa subscription -> beri Demo, 7 hari sejak anggota
        //    paling awal dibuat.
        $groupsWithout = DB::table('groups')
            ->leftJoin('subscriptions', 'subscriptions.group_id', '=', 'groups.id')
            ->whereNull('subscriptions.id')
            ->pluck('groups.id');

        foreach ($groupsWithout as $groupId) {
            $startedAt = DB::table('users')->where('group_id', $groupId)->min('created_at') ?? $now;
            $expiresAt = \Carbon\CarbonImmutable::parse($startedAt)->addWeek();

            DB::table('subscriptions')->insert([
                'group_id' => $groupId,
                'subscription_plan_id' => $demoId,
                'status' => $expiresAt->isFuture()
                    ? SubscriptionStatus::Active->value
                    : SubscriptionStatus::Expired->value,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus grup/langganan hasil backfill: data itu
        // sudah menjadi milik pengguna, dan menghapusnya justru merusak.
        // Paket Demo dibiarkan agar baris subscription yang menunjuk
        // padanya tidak menjadi yatim.
    }
};

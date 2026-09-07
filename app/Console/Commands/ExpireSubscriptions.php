<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Berjalan setiap hari lewat Task Scheduling.
 *
 * Menandai langganan yang sudah melewati tanggal kedaluwarsa sebagai
 * Expired. Sifatnya menyusulkan status di database saja -- pemeriksaan
 * saat runtime (Subscription::isEffectivelyActive/hasExpired) sudah
 * membandingkan expires_at dengan waktu sekarang, jadi keterlambatan
 * perintah ini tidak membuat langganan kedaluwarsa tetap dianggap aktif.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Tandai langganan yang sudah lewat tanggal kedaluwarsa sebagai Expired';

    public function handle(): int
    {
        $affected = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $this->info("{$affected} langganan ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}

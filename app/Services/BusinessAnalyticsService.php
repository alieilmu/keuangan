<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Group;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metrik bisnis inti untuk panel admin: MRR, churn rate, dan pertumbuhan
 * user. Dihitung langsung dari data produksi (bukan tabel snapshot
 * terpisah), jadi selalu mencerminkan kondisi saat ini.
 */
class BusinessAnalyticsService
{
    /**
     * Monthly Recurring Revenue: total harga paket berbayar milik grup yang
     * langganannya AKTIF dan belum lewat tanggal kedaluwarsa saat ini.
     * Paket Free tidak ikut terhitung karena price = 0.
     */
    public function mrr(): int
    {
        // Paket berdurasi (3/6/12 bulan) dinormalkan ke nilai per bulan,
        // termasuk slot anggota tambahan -- lihat Subscription::monthlyValue().
        return (int) round($this->activeSubscriptions()->sum(fn (Subscription $s) => $s->monthlyValue()));
    }

    /** @return \Illuminate\Support\Collection<int, Subscription> */
    private function activeSubscriptions(): \Illuminate\Support\Collection
    {
        return Subscription::query()
            ->with('plan')
            ->where('status', SubscriptionStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
    }

    /**
     * Churn rate bulan berjalan: proporsi langganan berbayar yang aktif di
     * AWAL bulan ini, tetapi kini berstatus dibatalkan atau sudah lewat
     * tanggal kedaluwarsa. Bulan tanpa pelanggan berbayar di awal periode
     * menghasilkan 0%, bukan pembagian dengan nol.
     */
    public function churnRate(): float
    {
        $startOfMonth = CarbonImmutable::now()->startOfMonth();

        $activeAtStart = Subscription::query()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->where('subscription_plans.price', '>', 0)
            ->where('subscriptions.started_at', '<', $startOfMonth)
            ->where(fn ($q) => $q->whereNull('subscriptions.canceled_at')->orWhere('subscriptions.canceled_at', '>=', $startOfMonth))
            ->count();

        if ($activeAtStart === 0) {
            return 0.0;
        }

        $churnedThisMonth = Subscription::query()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->where('subscription_plans.price', '>', 0)
            ->where('subscriptions.started_at', '<', $startOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->where('subscriptions.canceled_at', '>=', $startOfMonth)
                    ->orWhere(fn ($q2) => $q2->where('subscriptions.expires_at', '>=', $startOfMonth)
                        ->where('subscriptions.expires_at', '<', now()));
            })
            ->count();

        return round(($churnedThisMonth / $activeAtStart) * 100, 1);
    }

    /**
     * Pertumbuhan user 6 bulan terakhir: user baru per bulan dan kumulatif.
     * Bulan tanpa pendaftaran tetap muncul dengan nilai 0, bukan hilang
     * dari grafik, supaya tren tidak menyesatkan.
     *
     * @return Collection<int, array{month: string, label: string, new_users: int, cumulative: int}>
     */
    public function userGrowth(int $months = 6): Collection
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $perMonth = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $cumulativeBefore = User::query()->where('created_at', '<', $start)->count();

        $running = $cumulativeBefore;

        return collect(range(0, $months - 1))->map(function (int $i) use ($start, $perMonth, &$running) {
            $period = $start->addMonths($i);
            $key = $period->format('Y-m');
            $newUsers = (int) ($perMonth[$key] ?? 0);
            $running += $newUsers;

            return [
                'month' => $key,
                'label' => $period->translatedFormat('M Y'),
                'new_users' => $newUsers,
                'cumulative' => $running,
            ];
        });
    }

    /** Ringkasan angka untuk kartu KPI. */
    public function summary(): array
    {
        return [
            'mrr' => $this->mrr(),
            'churn_rate' => $this->churnRate(),
            'total_users' => User::query()->count(),
            'total_tenants' => Group::query()->count(),
            'paying_tenants' => $this->activeSubscriptions()
                ->filter(fn (Subscription $s) => $s->monthlyValue() > 0)
                ->count(),
        ];
    }
}

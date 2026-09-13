<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\Group;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penagihan langganan dari sisi pengguna: menghitung harga, menerapkan kode
 * diskon, membuat pesanan, dan -- setelah admin mengonfirmasi pembayaran --
 * menerapkannya ke langganan grup.
 *
 * Aturan periode:
 * - Perpanjang paket yang SAMA dan masih aktif: masa aktif ditambahkan dari
 *   tanggal kedaluwarsa berjalan, jadi sisa hari tidak hangus.
 * - Ganti paket (upgrade/downgrade) atau paket sudah habis: periode baru
 *   dimulai saat konfirmasi.
 * - Slot anggota tambahan ikut terbawa selama paket tujuannya berkuota, dan
 *   ditagih ulang setiap periode. Paket tanpa batas tidak butuh slot.
 * - Tambah slot di tengah periode ditagih sampai tanggal kedaluwarsa
 *   berjalan (per bulan kalender, dibulatkan ke atas, minimal 1 bulan).
 */
class SubscriptionBillingService
{
    /** Batas jumlah slot yang bisa dibeli dalam satu pesanan. */
    public const MAX_EXTRA_PER_ORDER = 20;

    /**
     * @return array{type: string, plan_id: int, months: int, extra_members: int, subtotal: int, discount: int, total: int, coupon: ?Coupon, lines: array<int, array{label: string, amount: int}>}
     */
    public function quotePlan(Group $group, PlanPrice $option, ?string $couponCode): array
    {
        $option->loadMissing('plan');
        $plan = $option->plan;

        if (! $option->is_active || ! $plan?->is_active || $plan->code === 'demo') {
            throw ValidationException::withMessages(['plan_price_id' => 'Paket ini sedang tidak dijual.']);
        }

        $extra = $plan->max_members !== null ? (int) ($group->subscription?->extra_members ?? 0) : 0;
        $this->assertFitsQuota($group, $plan->max_members, $extra, $plan->name);

        $lines = [['label' => "Paket {$plan->name} - {$option->months} bulan", 'amount' => $option->price]];
        $extraCost = $extra * $plan->extra_member_price * $option->months;

        if ($extraCost > 0) {
            $lines[] = ['label' => "{$extra} slot anggota tambahan x {$option->months} bulan", 'amount' => $extraCost];
        }

        return $this->finalize(OrderType::Plan, $plan->id, $option->months, $extra, $option->price + $extraCost, $couponCode, $lines);
    }

    /**
     * @return array{type: string, plan_id: int, months: int, extra_members: int, subtotal: int, discount: int, total: int, coupon: ?Coupon, lines: array<int, array{label: string, amount: int}>}
     */
    public function quoteAddMembers(Group $group, int $count, ?string $couponCode): array
    {
        $subscription = $group->subscription;
        $plan = $subscription?->plan;

        if ($subscription === null || $plan === null || ! $plan->sellsExtraMembers() || $subscription->isDemo()) {
            throw ValidationException::withMessages([
                'count' => 'Paket Anda saat ini tidak menjual slot anggota tambahan. Upgrade paket terlebih dahulu.',
            ]);
        }

        if (! $subscription->isEffectivelyActive()) {
            throw ValidationException::withMessages(['count' => 'Langganan sudah berakhir. Perpanjang paket terlebih dahulu.']);
        }

        if ($count < 1 || $count > self::MAX_EXTRA_PER_ORDER) {
            throw ValidationException::withMessages(['count' => 'Jumlah slot harus 1 sampai '.self::MAX_EXTRA_PER_ORDER.'.']);
        }

        $months = $this->remainingMonths($subscription);
        $subtotal = $count * $plan->extra_member_price * $months;
        $until = $subscription->expires_at?->translatedFormat('d M Y') ?? 'tanpa batas waktu';
        $lines = [[
            'label' => "{$count} slot anggota x Rp".number_format($plan->extra_member_price, 0, ',', '.')." x {$months} bulan (s/d {$until})",
            'amount' => $subtotal,
        ]];

        return $this->finalize(OrderType::AddMember, $plan->id, $months, $count, $subtotal, $couponCode, $lines);
    }

    /**
     * Bulan kalender tersisa sampai kedaluwarsa, dibulatkan ke atas, minimal 1.
     *
     * Sengaja per bulan kalender, bukan hari/30: paket 3 bulan (+-92 hari)
     * dibagi 30 menjadi 4 bulan tagihan slot -- kelebihan satu bulan.
     * Pembulatan 2 desimal meredam selisih jam/detik sejak konfirmasi.
     */
    public function remainingMonths(Subscription $subscription): int
    {
        if ($subscription->expires_at === null || $subscription->expires_at->isPast()) {
            return 1;
        }

        return max(1, (int) ceil(round(now()->floatDiffInMonths($subscription->expires_at), 2)));
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    public function createOrder(User $user, Group $group, array $quote): SubscriptionOrder
    {
        if ($group->orders()->where('status', OrderStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Masih ada pesanan yang menunggu pembayaran. Selesaikan atau batalkan dulu pesanan tersebut.',
            ]);
        }

        return SubscriptionOrder::query()->create([
            'reference' => 'INV-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'group_id' => $group->getKey(),
            'user_id' => $user->getKey(),
            'type' => $quote['type'],
            'subscription_plan_id' => $quote['plan_id'],
            'billing_months' => $quote['months'],
            'extra_members' => $quote['extra_members'],
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'coupon_id' => $quote['coupon']?->getKey(),
            'coupon_code' => $quote['coupon']?->code,
            'lines' => $quote['lines'],
            'status' => OrderStatus::Pending->value,
        ]);
    }

    /**
     * Terapkan pesanan yang sudah dibayar ke langganan grup. Kuota diperiksa
     * ULANG di sini karena jumlah anggota bisa berubah sejak pesanan dibuat.
     */
    public function confirm(SubscriptionOrder $order, User $admin): void
    {
        DB::transaction(function () use ($order, $admin): void {
            $order = SubscriptionOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['order' => 'Pesanan ini sudah diproses sebelumnya.']);
            }

            $group = $order->group;
            $subscription = $group->subscription;

            if ($order->type === OrderType::Plan) {
                $plan = $order->plan;

                if ($plan === null) {
                    throw ValidationException::withMessages(['order' => 'Paket pada pesanan ini sudah tidak ada.']);
                }

                $this->assertFitsQuota($group, $plan->max_members, $order->extra_members, $plan->name);

                $renewing = $subscription !== null
                    && $subscription->subscription_plan_id === $plan->id
                    && $subscription->isEffectivelyActive()
                    && $subscription->expires_at !== null;

                $base = $renewing ? CarbonImmutable::parse($subscription->expires_at) : CarbonImmutable::now();

                Subscription::query()->updateOrCreate(['group_id' => $group->getKey()], [
                    'subscription_plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active->value,
                    'started_at' => $renewing ? $subscription->started_at : now(),
                    'expires_at' => $base->addMonthsNoOverflow($order->billing_months),
                    'canceled_at' => null,
                    'changed_by' => $admin->getKey(),
                    'billing_months' => $order->billing_months,
                    'amount' => $order->total,
                    'extra_members' => $order->extra_members,
                ]);
            } else {
                if ($subscription === null) {
                    throw ValidationException::withMessages(['order' => 'Grup ini belum punya langganan.']);
                }

                // Nilai langganan per periode ikut naik sebesar harga slot
                // penuh per periode, supaya MRR mencerminkan tagihan rutin
                // berikutnya -- bukan nilai prorata yang dibayar sekarang.
                $periodValue = $subscription->amount ?? (int) round($subscription->monthlyValue() * $subscription->billing_months);

                $subscription->update([
                    'extra_members' => $subscription->extra_members + $order->extra_members,
                    'amount' => $periodValue + $order->extra_members * (int) $subscription->plan?->extra_member_price * $subscription->billing_months,
                    'changed_by' => $admin->getKey(),
                ]);
            }

            $order->update([
                'status' => OrderStatus::Paid->value,
                'paid_at' => now(),
                'confirmed_by' => $admin->getKey(),
            ]);
        });
    }

    public function cancel(SubscriptionOrder $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages(['order' => 'Hanya pesanan yang masih menunggu yang bisa dibatalkan.']);
        }

        $order->update(['status' => OrderStatus::Canceled->value, 'canceled_at' => now()]);
    }

    private function assertFitsQuota(Group $group, ?int $planMax, int $extra, string $planName): void
    {
        if ($planMax === null) {
            return;
        }

        $members = $group->users()->count();
        $quota = $planMax + $extra;

        if ($members > $quota) {
            throw ValidationException::withMessages([
                'plan_price_id' => "Grup Anda punya {$members} anggota, melebihi kuota paket {$planName} ({$quota}). "
                    .'Keluarkan anggota atau pilih paket yang lebih besar.',
            ]);
        }
    }

    /**
     * @param  array<int, array{label: string, amount: int}>  $lines
     */
    private function finalize(OrderType $type, int $planId, int $months, int $extra, int $subtotal, ?string $couponCode, array $lines): array
    {
        $coupon = null;
        $discount = 0;
        $code = strtoupper(trim((string) $couponCode));

        if ($code !== '') {
            $coupon = Coupon::query()->where('code', $code)->first();

            if ($coupon === null || ! $coupon->isUsable()) {
                throw ValidationException::withMessages(['coupon_code' => 'Kode diskon tidak valid atau sudah tidak berlaku.']);
            }

            // Berlaku juga untuk pembelian slot anggota: yang diperiksa adalah
            // paket langganan grup saat itu.
            if (! $coupon->appliesToPlan($planId)) {
                throw ValidationException::withMessages([
                    'coupon_code' => "Kode {$coupon->code} hanya berlaku untuk paket ".$coupon->plans->pluck('name')->join(', ', ' dan ').'.',
                ]);
            }

            $discount = $coupon->discountFor($subtotal);
            $lines[] = ['label' => "Kode {$coupon->code} ({$coupon->describe()})", 'amount' => -$discount];
        }

        return [
            'type' => $type->value,
            'plan_id' => $planId,
            'months' => $months,
            'extra_members' => $extra,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'coupon' => $coupon,
            'lines' => $lines,
        ];
    }
}

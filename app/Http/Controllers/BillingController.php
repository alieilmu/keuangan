<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Models\PlanPrice;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionBillingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Langganan" milik pengguna: melihat paket & tanggal tagihan
 * berikutnya, upgrade/perpanjang, beli slot anggota, dan memakai kode diskon.
 * Semua anggota grup boleh mengelola langganan kas bersamanya.
 */
class BillingController extends Controller
{
    public function __construct(private readonly SubscriptionBillingService $billing) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $group = $user->group;
        $subscription = $group?->subscription()->with('plan')->first();
        $plan = $subscription?->plan;

        return Inertia::render('Billing/Index', [
            'group' => $group ? [
                'name' => $group->name,
                'member_count' => $group->users()->count(),
                'member_quota' => $group->memberQuota(),
            ] : null,
            'subscription' => $subscription ? [
                'plan_id' => $plan?->id,
                'plan_name' => $plan?->name,
                'plan_code' => $plan?->code,
                'is_demo' => $subscription->isDemo(),
                'active' => $subscription->isEffectivelyActive(),
                'expired' => $subscription->hasExpired(),
                'remaining_days' => $subscription->remainingDays(),
                'expires_label' => $subscription->expires_at?->translatedFormat('d F Y'),
                'billing_months' => $subscription->billing_months,
                'amount' => $subscription->amount,
                'plan_max_members' => $plan?->max_members,
                'extra_members' => $subscription->extra_members,
                'extra_member_price' => $plan?->extra_member_price ?? 0,
                'sells_extra' => $plan?->sellsExtraMembers() && ! $subscription->isDemo() && $subscription->isEffectivelyActive(),
                'remaining_months' => $this->billing->remainingMonths($subscription),
            ] : null,
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->where('code', '!=', 'demo')
                ->with(['prices' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('price')
                ->get()
                ->filter(fn (SubscriptionPlan $p) => $p->prices->isNotEmpty())
                ->map(fn (SubscriptionPlan $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'max_members' => $p->max_members,
                    'extra_member_price' => $p->extra_member_price,
                    'features' => $p->features ?? [],
                    'prices' => $p->prices->map(fn (PlanPrice $price) => [
                        'id' => $price->id,
                        'months' => $price->months,
                        'label' => $price->label(),
                        'price' => $price->price,
                        'monthly' => (int) round($price->price / $price->months),
                    ])->values(),
                ])->values(),
            'orders' => $group
                ? $group->orders()->with(['plan', 'user'])->latest()->limit(10)->get()
                    ->map(fn (SubscriptionOrder $o) => $o->present())->values()
                : [],
            // Pratinjau harga (termasuk kode diskon) diminta lewat partial
            // reload dengan query string, tanpa membuat pesanan apa pun.
            'quote' => fn () => $this->previewQuote($request),
            'max_extra_per_order' => SubscriptionBillingService::MAX_EXTRA_PER_ORDER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([OrderType::Plan->value, OrderType::AddMember->value])],
            'plan_price_id' => ['required_if:type,plan', 'nullable', 'integer', 'exists:plan_prices,id'],
            'count' => ['required_if:type,add_member', 'nullable', 'integer', 'min:1', 'max:'.SubscriptionBillingService::MAX_EXTRA_PER_ORDER],
            'coupon_code' => ['nullable', 'string', 'max:30'],
        ]);

        $group = $request->user()->group;

        if ($group === null) {
            return back()->with('error', 'Akun Anda belum tergabung dalam grup mana pun.');
        }

        $quote = $validated['type'] === OrderType::Plan->value
            ? $this->billing->quotePlan($group, PlanPrice::query()->findOrFail($validated['plan_price_id']), $validated['coupon_code'] ?? null)
            : $this->billing->quoteAddMembers($group, (int) $validated['count'], $validated['coupon_code'] ?? null);

        $order = $this->billing->createOrder($request->user(), $group, $quote);

        return back()->with('success', "Pesanan {$order->reference} dibuat. Selesaikan pembayaran QRIS; langganan diperbarui setelah dikonfirmasi admin.");
    }

    public function cancel(Request $request, SubscriptionOrder $order): RedirectResponse
    {
        abort_unless($order->group_id === $request->user()->group_id, 404);

        try {
            $this->billing->cancel($order);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Pesanan {$order->reference} dibatalkan.");
    }

    /** @return array<string, mixed>|null */
    private function previewQuote(Request $request): ?array
    {
        $group = $request->user()->group;

        if ($group === null || ! $request->filled('preview')) {
            return null;
        }

        try {
            $quote = match ($request->query('preview')) {
                'plan' => $this->billing->quotePlan(
                    $group,
                    PlanPrice::query()->findOrFail((int) $request->query('plan_price_id')),
                    $request->query('coupon')
                ),
                'add_member' => $this->billing->quoteAddMembers($group, (int) $request->query('count'), $request->query('coupon')),
                default => null,
            };
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()];
        } catch (ModelNotFoundException) {
            return ['error' => 'Paket tidak ditemukan.'];
        }

        return $quote === null ? null : [
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'lines' => $quote['lines'],
            'coupon_code' => $quote['coupon']?->code,
        ];
    }
}

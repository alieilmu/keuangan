<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $subscriptions = Subscription::query()
            ->with(['group.users:id,group_id,name', 'plan'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Subscription $subscription) => $this->present($subscription));

        // Grup yang belum pernah punya baris subscription sama sekali
        // (mis. tenant baru) tetap perlu tampil, berstatus Free tersirat.
        $withoutSubscription = Group::query()
            ->doesntHave('subscription')
            ->with('users:id,group_id,name')
            ->get()
            ->map(fn (Group $group) => [
                'id' => null,
                'group_id' => $group->id,
                'group_name' => $group->name,
                'members' => $group->users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
                'member_count' => $group->users->count(),
                'member_quota' => null,
                'quota_full' => false,
                'plan_code' => null,
                'plan_name' => 'Belum berlangganan',
                'price' => 0,
                'is_demo' => false,
                'status' => null,
                'status_label' => 'Belum Ada',
                'expires_at' => null,
                'expires_label' => null,
                'remaining_days' => null,
                'expired' => false,
            ]);

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions->concat($withoutSubscription)->values(),
            'plans' => SubscriptionPlan::query()->with('prices')->orderBy('price')->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'max_members' => $plan->max_members,
                    'extra_member_price' => $plan->extra_member_price,
                    'is_active' => $plan->is_active,
                    'prices' => $plan->prices->map(fn ($price) => [
                        'id' => $price->id,
                        'months' => $price->months,
                        'price' => $price->price,
                        'is_active' => $price->is_active,
                    ])->values(),
                ])->values(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            // Kandidat yang bisa ditambahkan sebagai anggota grup: belum
            // tergabung grup mana pun dan bukan akun admin.
            'unassigned_users' => User::query()
                ->whereNull('group_id')
                ->where('is_admin', false)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Upgrade/downgrade paket sebuah grup. Bila grup belum pernah punya
     * baris subscription, baris baru dibuat (alur "mulai berlangganan").
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')],
            // Batas atas mencegah salah ketik tahun (mis. 2714) tersimpan
            // sebagai langganan yang praktis abadi.
            'expires_at' => ['nullable', 'date', 'after:today', 'before:2100-01-01'],
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $group = Group::findOrFail($validated['group_id']);

        // Menegakkan kuota SAAT downgrade: jangan sampai grup berakhir
        // dengan anggota melebihi batas paket barunya.
        if ($plan->max_members !== null && $group->users()->count() > $plan->max_members) {
            return back()->withErrors([
                'subscription_plan_id' => "Grup ini punya {$group->users()->count()} anggota, melebihi kuota paket {$plan->name} ({$plan->max_members}). Keluarkan anggota dulu sebelum downgrade.",
            ]);
        }

        DB::transaction(function () use ($validated, $plan, $request): void {
            Subscription::query()->updateOrCreate(
                ['group_id' => $validated['group_id']],
                [
                    'subscription_plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active->value,
                    'started_at' => now(),
                    'expires_at' => $plan->isFree() ? null : ($validated['expires_at'] ?? now()->addMonth()),
                    'canceled_at' => null,
                    'changed_by' => $request->user()->getKey(),
                ]
            );
        });

        return back()->with('success', "Paket grup diubah ke {$plan->name}.");
    }

    /** @return array<string, mixed> */
    private function present(Subscription $subscription): array
    {
        $group = $subscription->group;

        return [
            'id' => $subscription->id,
            'group_id' => $subscription->group_id,
            'group_name' => $group?->name,
            'members' => $group?->users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]) ?? collect(),
            'member_count' => $group?->users->count() ?? 0,
            'member_quota' => $group?->memberQuota(),
            'extra_members' => $subscription->extra_members,
            'quota_full' => $group ? ! $group->hasCapacityFor() : false,
            'plan_code' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'price' => $subscription->plan?->price ?? 0,
            'is_demo' => $subscription->isDemo(),
            'status' => $subscription->status->value,
            'status_label' => $subscription->isEffectivelyActive()
                ? $subscription->status->label()
                : SubscriptionStatus::Expired->label(),
            'expires_at' => $subscription->expires_at?->toDateString(),
            'expires_label' => $subscription->expires_at?->translatedFormat('d M Y'),
            'remaining_days' => $subscription->remainingDays(),
            'expired' => $subscription->hasExpired(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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
                'members' => $group->users->pluck('name'),
                'plan_code' => null,
                'plan_name' => 'Belum berlangganan',
                'price' => 0,
                'status' => null,
                'status_label' => 'Belum Ada',
                'expires_at' => null,
                'expires_label' => null,
            ]);

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions->concat($withoutSubscription)->values(),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('price')->get(
                ['id', 'code', 'name', 'price']
            ),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
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
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

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
        return [
            'id' => $subscription->id,
            'group_id' => $subscription->group_id,
            'group_name' => $subscription->group?->name,
            'members' => $subscription->group?->users->pluck('name') ?? collect(),
            'plan_code' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'price' => $subscription->plan?->price ?? 0,
            'status' => $subscription->status->value,
            'status_label' => $subscription->isEffectivelyActive()
                ? $subscription->status->label()
                : SubscriptionStatus::Expired->label(),
            'expires_at' => $subscription->expires_at?->toDateString(),
            'expires_label' => $subscription->expires_at?->translatedFormat('d M Y'),
        ];
    }
}

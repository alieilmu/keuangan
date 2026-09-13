<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => Coupon::query()->with('plans:id,name')->latest()->get()->map(fn (Coupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'description' => $c->description,
                'type' => $c->type->value,
                'value' => $c->value,
                'summary' => $c->describe(),
                'max_uses' => $c->max_uses,
                'used' => $c->usedCount(),
                'starts_at' => $c->starts_at?->toDateString(),
                'ends_at' => $c->ends_at?->toDateString(),
                'period_label' => trim(($c->starts_at?->translatedFormat('d M Y') ?? '').' - '.($c->ends_at?->translatedFormat('d M Y') ?? ''), ' -') ?: 'Tanpa batas waktu',
                'is_active' => $c->is_active,
                'usable' => $c->isUsable(),
                'plan_ids' => $c->plans->pluck('id')->values(),
                'plan_names' => $c->plans->pluck('name')->values(),
            ])->values(),
            // Paket Demo tidak dijual, jadi tidak ditawarkan sebagai pilihan.
            'plans' => SubscriptionPlan::query()->where('code', '!=', 'demo')->orderBy('price')->get(['id', 'name']),
            'types' => collect(CouponType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $planIds = $data['plan_ids'] ?? [];
        unset($data['plan_ids']);

        $coupon = Coupon::query()->create($data + ['is_active' => true]);
        $coupon->plans()->sync($planIds);

        return back()->with('success', "Kode diskon {$data['code']} dibuat.");
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        if ($request->boolean('toggle_only')) {
            $coupon->update(['is_active' => ! $coupon->is_active]);

            return back()->with('success', "Kode {$coupon->code} ".($coupon->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
        }

        $data = $this->validated($request, $coupon);
        $planIds = $data['plan_ids'] ?? [];
        unset($data['plan_ids']);

        $coupon->update($data);

        if ($request->has('plan_ids')) {
            $coupon->plans()->sync($planIds);
        }

        return back()->with('success', "Kode diskon {$coupon->code} diperbarui.");
    }

    /** Kupon yang pernah dipakai tidak dihapus -- riwayat pesanan tetap menunjuk padanya. */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->orders()->exists()) {
            $coupon->update(['is_active' => false]);

            return back()->with('success', "Kode {$coupon->code} sudah pernah dipakai, jadi dinonaktifkan (bukan dihapus).");
        }

        $coupon->delete();

        return back()->with('success', "Kode {$coupon->code} dihapus.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:1', Rule::when($request->input('type') === CouponType::Percent->value, ['max:100'])],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', Rule::exists('subscription_plans', 'id')->where(fn ($q) => $q->where('code', '!=', 'demo'))],
        ], [
            'code.regex' => 'Kode hanya boleh huruf, angka, strip, atau garis bawah.',
            'value.max' => 'Diskon persen maksimal 100.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Katalog paket (Free/Premium) dikelola sebagai data, bukan kode -- admin
 * bisa mengubah kuota anggota dan harga tanpa deploy ulang. Kode paket
 * ('free'/'premium') tidak bisa diubah karena beberapa logika (mis. MRR,
 * churn) membedakan paket berbayar lewat price > 0, bukan lewat kode.
 */
class SubscriptionPlanController extends Controller
{
    /** Paket baru. Paket berbayar langsung mendapat opsi durasi 1 bulan. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'integer', 'min:0'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'extra_member_price' => ['required', 'integer', 'min:0'],
        ]);

        $base = Str::slug($data['name'], '_') ?: 'paket';
        $code = $base;

        for ($i = 2; SubscriptionPlan::query()->where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        $plan = SubscriptionPlan::query()->create($data + ['code' => $code, 'features' => [], 'is_active' => true]);

        if ($plan->price > 0) {
            $plan->prices()->create(['months' => 1, 'price' => $plan->price]);
        }

        return back()->with('success', "Paket {$plan->name} dibuat.");
    }

    public function storePrice(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->code === 'demo') {
            return back()->with('error', 'Paket Demo adalah masa coba dan tidak dijual.');
        }

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36', Rule::unique('plan_prices', 'months')->where('subscription_plan_id', $plan->id)],
            'price' => ['required', 'integer', 'min:1'],
        ], ['months.unique' => 'Durasi ini sudah ada untuk paket tersebut.']);

        $plan->prices()->create($data);

        return back()->with('success', "Opsi {$data['months']} bulan ditambahkan ke paket {$plan->name}.");
    }

    public function updatePrice(Request $request, PlanPrice $price): RedirectResponse
    {
        $price->update($request->validate([
            'price' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Opsi durasi diperbarui.');
    }

    /** Aman dihapus: pesanan menyimpan snapshot durasi & harganya sendiri. */
    public function destroyPrice(PlanPrice $price): RedirectResponse
    {
        $price->delete();

        return back()->with('success', 'Opsi durasi dihapus.');
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'integer', 'min:0'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'extra_member_price' => ['required', 'integer', 'min:0'],
        ]);

        // Menyempitkan kuota di bawah pemakaian grup yang sudah ada akan
        // membuat grup itu "melebihi kuota" tanpa jalan keluar (baru bisa
        // ditambah anggota lagi setelah admin mengeluarkan sebagian).
        // Itu situasi yang sah untuk plafon baru, jadi tidak diblokir --
        // hanya diberi tahu supaya admin sadar dampaknya.
        $plan->update($validated);

        $overLimit = $plan->max_members === null
            ? 0
            : $plan->subscriptions()->whereHas(
                'group',
                fn ($q) => $q->has('users', '>', $plan->max_members)
            )->count();

        $message = "Paket {$plan->name} diperbarui.";

        if ($overLimit > 0) {
            $message .= " Perhatian: {$overLimit} grup kini melebihi kuota baru ({$plan->max_members} anggota).";
        }

        return back()->with('success', $message);
    }
}

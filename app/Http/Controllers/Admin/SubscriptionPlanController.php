<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Katalog paket (Free/Premium) dikelola sebagai data, bukan kode -- admin
 * bisa mengubah kuota anggota dan harga tanpa deploy ulang. Kode paket
 * ('free'/'premium') tidak bisa diubah karena beberapa logika (mis. MRR,
 * churn) membedakan paket berbayar lewat price > 0, bukan lewat kode.
 */
class SubscriptionPlanController extends Controller
{
    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'integer', 'min:0'],
            'max_members' => ['nullable', 'integer', 'min:1'],
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

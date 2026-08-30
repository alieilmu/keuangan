<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentSimulationStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentSimulation;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demonstrasi alur pembayaran QRIS Doku. TIDAK ADA koneksi ke gateway
 * sungguhan -- kode QR yang ditampilkan adalah data statis contoh, dan
 * status "berhasil" hanya dipicu manual oleh admin lewat tombol simulasi,
 * meniru webhook yang pada integrasi asli akan dikirim Doku.
 */
class PaymentSimulationController extends Controller
{
    public function index(): Response
    {
        $history = PaymentSimulation::query()
            ->with('subscription.group', 'subscription.plan')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (PaymentSimulation $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'group_name' => $payment->subscription->group?->name,
                'plan_name' => $payment->subscription->plan?->name,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'created_at' => $payment->created_at?->translatedFormat('d M Y H:i'),
            ]);

        $pendingSubscriptions = Subscription::query()
            ->with(['group', 'plan'])
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'group_name' => $subscription->group?->name,
                'plan_name' => $subscription->plan?->name,
                'price' => $subscription->plan?->price,
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'history' => $history,
            'subscriptions' => $pendingSubscriptions,
        ]);
    }

    /** Buat "tagihan" QRIS simulasi untuk sebuah langganan. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'integer', Rule::exists('subscriptions', 'id')],
        ]);

        $subscription = Subscription::with('plan')->findOrFail($validated['subscription_id']);

        PaymentSimulation::query()->create([
            'subscription_id' => $subscription->id,
            'method' => 'qris_doku',
            'amount' => $subscription->plan->price,
            'status' => PaymentSimulationStatus::Pending->value,
            'reference' => 'DOKU-SIM-'.strtoupper(Str::random(10)),
        ]);

        return back()->with('success', 'QRIS simulasi dibuat. Silakan simulasikan pembayarannya.');
    }

    /**
     * Tandai QRIS "dibayar": memperbarui status pembayaran DAN memperpanjang
     * masa aktif langganan satu bulan -- persis alur yang akan dijalankan
     * webhook Doku sungguhan saat integrasi nyata dipasang nanti.
     */
    public function markPaid(PaymentSimulation $payment): RedirectResponse
    {
        if ($payment->status !== PaymentSimulationStatus::Pending) {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($payment): void {
            $payment->update(['status' => PaymentSimulationStatus::Success->value, 'simulated_at' => now()]);

            $subscription = $payment->subscription;
            $subscription->update([
                'status' => SubscriptionStatus::Active->value,
                'expires_at' => now()->addMonth(),
                'canceled_at' => null,
            ]);
        });

        return back()->with('success', 'Pembayaran disimulasikan berhasil. Langganan diperpanjang 1 bulan.');
    }

    public function markFailed(PaymentSimulation $payment): RedirectResponse
    {
        if ($payment->status !== PaymentSimulationStatus::Pending) {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        $payment->update(['status' => PaymentSimulationStatus::Failed->value, 'simulated_at' => now()]);

        return back()->with('success', 'Pembayaran disimulasikan gagal.');
    }
}

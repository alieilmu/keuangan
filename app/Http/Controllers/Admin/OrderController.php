<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionOrder;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Konfirmasi pembayaran pesanan langganan. Pembayaran masih simulasi QRIS:
 * tombol konfirmasi di sini menggantikan webhook yang akan dikirim gateway
 * pembayaran sungguhan.
 */
class OrderController extends Controller
{
    public function __construct(private readonly SubscriptionBillingService $billing) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Orders/Index', [
            'orders' => SubscriptionOrder::query()
                ->with(['group:id,name', 'user:id,name', 'plan:id,name'])
                ->orderByRaw("FIELD(status, 'pending', 'paid', 'canceled')")
                ->latest()
                ->paginate(20)
                ->through(fn (SubscriptionOrder $o) => $o->present()),
            'pending_count' => SubscriptionOrder::query()->where('status', OrderStatus::Pending->value)->count(),
        ]);
    }

    public function confirm(Request $request, SubscriptionOrder $order): RedirectResponse
    {
        try {
            $this->billing->confirm($order, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Pembayaran {$order->reference} dikonfirmasi. Langganan {$order->group?->name} diperbarui.");
    }

    public function cancel(SubscriptionOrder $order): RedirectResponse
    {
        try {
            $this->billing->cancel($order);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Pesanan {$order->reference} dibatalkan.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function store(TransferRequest $request): RedirectResponse
    {
        $transfer = $this->transfers->create($request->user(), $request->validated());

        $transfer->loadMissing(['fromAccount', 'toAccount']);

        return back()->with('success', sprintf(
            'Transfer %s dari %s ke %s berhasil dicatat.',
            'Rp '.number_format((float) $transfer->amount, 0, ',', '.'),
            $transfer->fromAccount?->name ?? 'akun asal',
            $transfer->toAccount?->name ?? 'akun tujuan',
        ));
    }

    public function destroy(Transfer $transfer): RedirectResponse
    {
        $this->authorize('delete', $transfer);

        if ($transfer->savings_goal_id !== null) {
            return back()->with(
                'error',
                'Transfer ini berasal dari setoran tabungan. Batalkan lewat tagihannya agar progres tabungan ikut disesuaikan.'
            );
        }

        $this->transfers->reverse($transfer);

        return back()->with('success', 'Transfer dibatalkan dan saldo kedua akun dikembalikan.');
    }
}

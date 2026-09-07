<?php

namespace App\Http\Controllers;

use App\Services\UserDataResetService;
use App\Services\WalkthroughService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalkthroughController extends Controller
{
    public function __construct(
        private readonly WalkthroughService $walkthrough,
        private readonly UserDataResetService $reset,
    ) {}

    /**
     * Maju ke langkah berikutnya. Langkah yang punya syarat data hanya
     * boleh dilewati bila syaratnya benar-benar terpenuhi -- pemeriksaan
     * dilakukan di server, bukan sekadar mengandalkan tombol di antarmuka
     * yang bisa saja dilewati.
     */
    public function advance(Request $request): RedirectResponse
    {
        $user = $request->user();
        $step = (int) $user->walkthrough_step;

        if ($step === 0) {
            return back();
        }

        if (! $this->walkthrough->isStepSatisfied($user, $step)) {
            return back()->with('error', 'Selesaikan dulu langkah ini sebelum melanjutkan.');
        }

        if ($step >= WalkthroughService::TOTAL_STEPS) {
            return back();
        }

        $user->update(['walkthrough_step' => $step + 1]);

        return back();
    }

    /** Keluar dari panduan tanpa menyelesaikannya. Data tetap dibiarkan. */
    public function skip(Request $request): RedirectResponse
    {
        $request->user()->update([
            'walkthrough_step' => null,
            'walkthrough_skipped_at' => now(),
        ]);

        return back()->with('success', 'Panduan ditutup. Anda bisa mulai mencatat kapan saja.');
    }

    /**
     * Menutup panduan di langkah terakhir, dengan dua pilihan:
     * - keep  : data latihan dipertahankan
     * - reset : seluruh data keuangan dikosongkan, akun & kategori bawaan
     *           disiapkan ulang seperti akun yang baru dibuat
     */
    public function finish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', Rule::in(['keep', 'reset'])],
        ]);

        $user = $request->user();

        if ($validated['choice'] === 'reset') {
            $this->reset->reset($user);
        }

        $user->update([
            'walkthrough_step' => null,
            'walkthrough_completed_at' => now(),
        ]);

        return redirect('/dashboard')->with(
            'success',
            $validated['choice'] === 'reset'
                ? 'Data latihan dikosongkan. Silakan mulai mencatat keuangan Anda.'
                : 'Panduan selesai. Data yang tadi Anda buat tetap tersimpan.'
        );
    }
}

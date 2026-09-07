<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $users = User::query()
            ->with(['group.subscription.plan'])
            ->withCount(['accounts', 'transactions'])
            ->when($search, fn ($query, $term) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['group.subscription.plan', 'group.users']);
        $user->loadCount(['accounts', 'transactions', 'bills']);

        return Inertia::render('Admin/Users/Show', [
            'user' => array_merge($this->present($user), [
                'group_members' => $user->group?->users
                    ->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])
                    ->values(),
                'bills_count' => $user->bills_count,
                'created_at' => $user->created_at?->translatedFormat('d M Y'),
            ]),
        ]);
    }

    /** Blokir / aktifkan kembali sebuah akun. Admin tidak bisa memblokir dirinya sendiri. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa memblokir akun sendiri.');
        }

        $user->update([
            'is_blocked' => ! $user->is_blocked,
            'blocked_at' => $user->is_blocked ? null : now(),
        ]);

        return back()->with('success', $user->is_blocked ? 'Akun diblokir.' : 'Akun diaktifkan kembali.');
    }

    /**
     * Buat kata sandi acak baru dan tampilkan SEKALI ke admin lewat flash
     * session. Bukan email reset link, karena deployment ini belum punya
     * pengiriman email sungguhan (MAIL_MAILER=log) -- admin menyampaikan
     * kata sandi ini langsung ke pengguna lewat jalur lain.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $newPassword = Str::password(12, symbols: false);

        $user->update(['password' => $newPassword]);

        return back()->with('success', 'Kata sandi baru dibuat.')
            ->with('generated_password', $newPassword);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        $subscription = $user->group?->subscription;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'is_admin' => $user->is_admin,
            'is_blocked' => $user->is_blocked,
            'group_id' => $user->group_id,
            'group_name' => $user->group?->name,
            'plan_name' => $subscription?->plan?->name ?? 'Tanpa Grup',
            'plan_code' => $subscription?->plan?->code,
            'is_demo' => $subscription?->isDemo() ?? false,
            'remaining_days' => $subscription?->remainingDays(),
            'expired' => $subscription?->hasExpired() ?? false,
            'expires_label' => $subscription?->expires_at?->translatedFormat('d M Y'),
            'accounts_count' => $user->accounts_count,
            'transactions_count' => $user->transactions_count,
            'joined_at' => $user->created_at?->translatedFormat('d M Y'),
        ];
    }
}

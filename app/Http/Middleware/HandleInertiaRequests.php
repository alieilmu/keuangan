<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\WalkthroughService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props yang tersedia di seluruh halaman.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'import_preview' => fn () => $request->session()->get('import_preview'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
            ],
            // Hanya jumlah belum-dibaca yang dikirim di setiap halaman, untuk
            // badge lonceng. Isi daftarnya ada di 'notification_items'.
            'notifications' => fn () => [
                'unread' => $user ? $user->unreadNotifications()->count() : 0,
            ],
            // Prop OPSIONAL: tidak ikut dalam muatan halaman biasa, hanya
            // dikirim bila diminta eksplisit lewat partial reload saat lonceng
            // dibuka. Sebelumnya daftar ini ikut di setiap navigasi -- sekitar
            // sepertiga payload dashboard plus satu query -- padahal hanya
            // dipakai ketika dropdown notifikasi terbuka.
            'notification_items' => Inertia::optional(fn () => $user
                ? $user->notifications()->latest()->limit(10)->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                        'created_label' => $notification->created_at?->diffForHumans(),
                        'data' => $notification->data,
                    ])
                    ->values()
                : []),
            'push' => [
                'vapid_public_key' => config('webpush.vapid.public_key'),
            ],
            // Status langganan tenant milik user, dipakai banner masa coba
            // di dashboard. Lazy supaya query-nya tidak jalan untuk tamu.
            'subscription' => fn () => $this->subscriptionProps($user),
            // Panduan interaktif pengguna baru.
            'walkthrough' => fn () => app(WalkthroughService::class)->present($user),
            // Grup (kas bersama) + kuota anggotanya, untuk modal
            // "Tambah Anggota" di menu profil.
            'group' => fn () => $this->groupProps($user),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionProps(?User $user): ?array
    {
        $subscription = $user?->group?->subscription;

        if ($subscription === null) {
            return null;
        }

        $subscription->loadMissing('plan');

        return [
            'plan_code' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'is_demo' => $subscription->isDemo(),
            'remaining_days' => $subscription->remainingDays(),
            'expired' => $subscription->hasExpired(),
            'expires_label' => $subscription->expires_at?->translatedFormat('d M Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function groupProps(?User $user): ?array
    {
        $group = $user?->group;

        if ($group === null) {
            return null;
        }

        $quota = $group->memberQuota();
        $members = $group->users()->orderBy('name')->get(['id', 'name', 'email']);

        return [
            'id' => $group->getKey(),
            'name' => $group->name,
            'plan_name' => $group->subscription?->plan?->name,
            'member_count' => $members->count(),
            'member_quota' => $quota,
            // Setara dengan ! $group->hasCapacityFor(), tetapi dihitung dari
            // daftar anggota yang sudah dimuat di atas -- tanpa query COUNT
            // tambahan di setiap halaman untuk grup yang punya batas kuota.
            'quota_full' => $quota !== null && $members->count() >= $quota,
            'members' => $members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'is_self' => $member->is($user),
            ])->values(),
        ];
    }
}

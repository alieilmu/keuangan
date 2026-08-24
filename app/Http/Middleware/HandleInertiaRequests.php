<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
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
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'import_failures' => fn () => $request->session()->get('import_failures'),
            ],
            // Lazy prop: query notifikasi hanya jalan saat user sudah login.
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->latest()->limit(10)->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                        'created_label' => $notification->created_at?->diffForHumans(),
                        'data' => $notification->data,
                    ]),
            ] : ['unread' => 0, 'items' => []],
            'push' => [
                'vapid_public_key' => config('webpush.vapid.public_key'),
            ],
        ]);
    }
}

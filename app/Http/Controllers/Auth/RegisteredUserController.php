<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DefaultDataProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly DefaultDataProvisioner $provisioner) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create($data);

            // Setiap akun baru menjadi tenant-nya sendiri, lengkap dengan
            // langganan Demo berdurasi satu pekan. Sebelumnya pendaftaran
            // tidak membuat grup maupun subscription sama sekali, sehingga
            // ada akun yang memakai aplikasi tanpa batas waktu.
            $group = Group::query()->create(['name' => 'Kas '.$data['name']]);

            $user->update([
                'group_id' => $group->getKey(),
                // Mulai dari langkah pertama panduan interaktif.
                'walkthrough_step' => 1,
            ]);

            $demo = SubscriptionPlan::query()->where('code', 'demo')->first();

            if ($demo !== null) {
                $group->subscription()->create([
                    'subscription_plan_id' => $demo->getKey(),
                    'status' => SubscriptionStatus::Active->value,
                    'started_at' => now(),
                    'expires_at' => now()->addWeek(),
                ]);
            }

            $this->provisioner->provision($user);

            return $user;
        });

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

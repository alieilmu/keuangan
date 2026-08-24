<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

            $this->provisioner->provision($user);

            return $user;
        });

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

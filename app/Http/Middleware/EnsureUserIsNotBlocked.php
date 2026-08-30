<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan status blokir pada SETIAP request, bukan hanya saat login.
 * Tanpa ini, admin memblokir akun tapi sesi yang sedang berjalan tetap
 * bisa dipakai sampai kedaluwarsa sendiri (hingga 120 menit).
 */
class EnsureUserIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_blocked) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini telah diblokir. Hubungi admin.',
            ]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DefaultDataProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Menambah anggota baru ke dalam grup (kas bersama) milik pengguna yang
 * sedang login -- dipakai dari menu profil, bukan dari panel admin.
 *
 * Berbeda dengan Admin\GroupController yang MEMINDAHKAN user tanpa grup ke
 * sebuah grup, di sini akun anggotanya benar-benar DIBUAT baru lengkap
 * dengan akun dana & kategori bawaan, supaya pengundang tidak perlu
 * menunggu orang tersebut mendaftar sendiri lebih dulu.
 */
class GroupMemberController extends Controller
{
    public function __construct(private readonly DefaultDataProvisioner $provisioner) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $group = $user->group;

        if ($group === null) {
            return back()->with('error', 'Akun Anda belum tergabung dalam grup mana pun.');
        }

        // Kuota diperiksa SEBELUM validasi input, supaya pengguna yang
        // kuotanya penuh langsung diberi tahu tanpa perlu mengisi form
        // sampai selesai.
        if (! $group->hasCapacityFor()) {
            $quota = $group->memberQuota();
            $planName = $group->subscription?->plan?->name ?? 'saat ini';

            return back()->with(
                'error',
                "Kuota paket {$planName} sudah penuh ({$quota} anggota). Hubungi admin untuk menaikkan paket."
            );
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        DB::transaction(function () use ($data, $group): void {
            $member = User::query()->create($data);

            $member->update(['group_id' => $group->getKey()]);

            // Anggota baru langsung punya akun dana & kategori bawaan.
            // Panduan interaktif TIDAK dinyalakan: ia bergabung ke kas yang
            // sudah berjalan, bukan memulai dari nol seperti pendaftar baru.
            $this->provisioner->provision($member);
        });

        return back()->with('success', "{$data['name']} berhasil ditambahkan ke {$group->name}.");
    }
}

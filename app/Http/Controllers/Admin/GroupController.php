<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Keanggotaan grup/keluarga -- satu-satunya jalur user berpindah masuk
 * atau keluar dari sebuah grup. Menegakkan kuota paket di titik ini,
 * karena inilah tempat jumlah anggota benar-benar bisa bertambah.
 */
class GroupController extends Controller
{
    /** Tambahkan user yang belum tergabung grup mana pun ke grup ini. */
    public function addMember(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->group_id !== null) {
            return back()->with('error', "{$user->name} sudah tergabung di grup lain.");
        }

        if ($user->is_admin) {
            return back()->with('error', 'Akun admin tidak bisa dijadikan anggota grup.');
        }

        if (! $group->hasCapacityFor()) {
            $quota = $group->memberQuota();
            $planName = $group->subscription?->plan?->name ?? 'saat ini';

            return back()->with('error', "Kuota paket {$planName} sudah penuh ({$quota} anggota). Upgrade paket atau keluarkan anggota lain dulu.");
        }

        $user->update(['group_id' => $group->id]);

        return back()->with('success', "{$user->name} ditambahkan ke {$group->name}.");
    }

    /**
     * Keluarkan user dari grup. Data keuangan miliknya TIDAK dihapus atau
     * dipindahkan -- hanya tautan group_id-nya dilepas, sehingga user itu
     * kembali terisolasi sebagai pengguna tunggal (lihat User::visibleUserIds).
     */
    public function removeMember(Group $group, User $user): RedirectResponse
    {
        if ($user->group_id !== $group->id) {
            return back()->with('error', 'User ini bukan anggota grup tersebut.');
        }

        if ($group->users()->count() <= 1) {
            return back()->with('error', 'Grup harus punya minimal satu anggota.');
        }

        $user->update(['group_id' => null]);

        return back()->with('success', "{$user->name} dikeluarkan dari {$group->name}.");
    }
}

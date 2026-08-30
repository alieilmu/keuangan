<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    private const EMAIL = 'admin@keuangan.test';

    public function run(): void
    {
        $existing = User::query()->where('email', self::EMAIL)->first();

        if ($existing) {
            $existing->update(['is_admin' => true]);
            $this->command?->info('Admin sudah ada, is_admin dipastikan aktif: '.self::EMAIL);

            return;
        }

        $password = Str::password(14, symbols: false);

        User::query()->create([
            'name' => 'Admin',
            'email' => self::EMAIL,
            'password' => Hash::make($password),
            'is_admin' => true,
            'group_id' => null, // admin tidak ikut dalam tenant/kas bersama mana pun
        ]);

        $this->command?->warn('=== AKUN ADMIN DIBUAT ===');
        $this->command?->warn('Email    : '.self::EMAIL);
        $this->command?->warn('Password : '.$password);
        $this->command?->warn('Simpan sekarang -- tidak akan ditampilkan lagi.');
    }
}

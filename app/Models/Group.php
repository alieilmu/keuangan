<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kas bersama yang dipakai beberapa user.
 *
 * Anggota group melihat dan mengelola kumpulan data keuangan yang sama.
 * Kolom user_id pada data tetap mencatat siapa yang menginput, sehingga
 * riwayat per orang tidak hilang.
 */
#[Fillable(['name'])]
class Group extends Model
{
    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

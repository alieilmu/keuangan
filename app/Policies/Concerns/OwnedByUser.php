<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua entitas keuangan memakai aturan yang sama: baris boleh dilihat dan
 * diubah oleh siapa pun yang berada dalam satu group (kas bersama) dengan
 * pemilik baris.
 *
 * Kolom user_id tetap menyimpan siapa yang menginput, jadi riwayat per orang
 * tidak hilang; yang dilebarkan hanya cakupan aksesnya. User tanpa group
 * hanya bisa menjangkau datanya sendiri.
 */
trait OwnedByUser
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    protected function owns(User $user, Model $model): bool
    {
        $ownerId = $model->getAttribute('user_id');

        return $user->canReach($ownerId === null ? null : (int) $ownerId);
    }
}

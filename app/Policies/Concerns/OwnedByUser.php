<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua entitas keuangan memakai aturan yang sama: hanya pemilik baris
 * (kolom user_id) yang boleh melihat dan mengubahnya.
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
        return (int) $model->getAttribute('user_id') === (int) $user->getKey();
    }
}

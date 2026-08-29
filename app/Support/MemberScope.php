<?php

namespace App\Support;

use App\Models\User;

/**
 * Pemilih tampilan pada kas bersama: "Punya Saya / Pasangan / Gabungan".
 *
 * Cakupan maksimal selalu dibatasi anggota group user yang sedang login,
 * sehingga nilai apa pun dari query string tidak bisa dipakai untuk mengintip
 * data di luar group.
 */
final class MemberScope
{
    public const MINE = 'mine';

    public const PARTNER = 'partner';

    public const ALL = 'all';

    /**
     * Terjemahkan pilihan tampilan menjadi daftar user_id yang ditampilkan.
     *
     * @return array<int, int>
     */
    public static function resolve(User $user, ?string $scope): array
    {
        return match ($scope) {
            self::MINE => [(int) $user->getKey()],
            // Bila belum punya pasangan dalam group, jangan diam-diam
            // menampilkan data sendiri: kembalikan himpunan kosong.
            self::PARTNER => $user->partnerUserIds() ?: [0],
            default => $user->visibleUserIds(),
        };
    }

    /** Normalkan nilai query string ke salah satu pilihan yang sah. */
    public static function normalize(?string $scope): string
    {
        return in_array($scope, [self::MINE, self::PARTNER, self::ALL], true)
            ? $scope
            : self::ALL;
    }

    /**
     * Pilihan yang ditawarkan ke antarmuka. Opsi "Pasangan" disembunyikan
     * bila user memang sendirian di group-nya.
     *
     * @return array<int, array<string, string>>
     */
    public static function options(User $user): array
    {
        $options = [
            ['value' => self::ALL, 'label' => 'Gabungan'],
            ['value' => self::MINE, 'label' => 'Punya Saya'],
        ];

        $partners = $user->partnerUserIds();

        if ($partners !== []) {
            $names = User::query()->whereIn('id', $partners)->orderBy('id')->pluck('name');

            $options[] = [
                'value' => self::PARTNER,
                'label' => $names->count() === 1 ? $names->first() : 'Anggota Lain',
            ];
        }

        return $options;
    }
}

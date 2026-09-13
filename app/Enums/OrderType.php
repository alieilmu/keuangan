<?php

namespace App\Enums;

enum OrderType: string
{
    case Plan = 'plan';
    case AddMember = 'add_member';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Langganan Paket',
            self::AddMember => 'Tambah Slot Anggota',
        };
    }
}

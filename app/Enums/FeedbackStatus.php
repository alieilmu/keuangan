<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Read => 'Dibaca',
            self::Resolved => 'Selesai',
        };
    }
}

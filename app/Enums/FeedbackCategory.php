<?php

namespace App\Enums;

enum FeedbackCategory: string
{
    case Suggestion = 'saran';
    case Bug = 'bug';
    case Question = 'pertanyaan';
    case Other = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Suggestion => 'Saran',
            self::Bug => 'Laporan Masalah',
            self::Question => 'Pertanyaan',
            self::Other => 'Lainnya',
        };
    }
}

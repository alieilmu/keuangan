<?php

namespace App\Console\Commands;

use App\Services\SavingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Berjalan setiap hari lewat Task Scheduling.
 * Membuat tagihan setoran bulanan untuk tiap target tabungan yang aktif.
 */
class GenerateSavingsBills extends Command
{
    protected $signature = 'savings:generate-bills {--date= : Tanggal acuan (Y-m-d), default hari ini}';

    protected $description = 'Buat tagihan setoran bulanan dari target tabungan yang berjalan';

    public function handle(SavingsService $savings): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $created = $savings->generateForAll($today);

        $this->info("Tagihan setoran tabungan dibuat: {$created}");

        return self::SUCCESS;
    }
}

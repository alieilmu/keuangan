<?php

namespace App\Console\Commands;

use App\Services\CreditService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Berjalan setiap hari lewat Task Scheduling.
 * Membuat entri tagihan bulanan untuk setiap kredit aktif berdasarkan
 * monthly_installment dan tanggal jatuh tempo kreditnya.
 */
class GenerateCreditBills extends Command
{
    protected $signature = 'credits:generate-bills {--date= : Tanggal acuan (Y-m-d), default hari ini}';

    protected $description = 'Buat tagihan angsuran bulanan dari kredit yang masih berjalan';

    public function handle(CreditService $credits): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $created = $credits->generateForAll($today);

        $this->info("Tagihan angsuran dibuat: {$created}");

        return self::SUCCESS;
    }
}

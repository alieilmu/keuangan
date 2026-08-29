<?php

use App\Console\Commands\CheckBudgetThresholds;
use App\Console\Commands\GenerateCreditBills;
use App\Console\Commands\GenerateSavingsBills;
use App\Console\Commands\RemindDueBills;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Task Scheduling
|--------------------------------------------------------------------------
| Container "scheduler" pada docker-compose menjalankan `php artisan schedule:work`
| sehingga kedua perintah di bawah berjalan otomatis setiap hari tanpa cron host.
*/

// Tagihan angsuran kredit dibuat lebih dulu, supaya pengingat di bawah
// langsung ikut memperhitungkan tagihan yang baru terbentuk hari itu.
Schedule::command(GenerateCreditBills::class)
    ->dailyAt('06:50')
    ->withoutOverlapping()
    ->onOneServer();

// Tagihan setoran tabungan terencana.
Schedule::command(GenerateSavingsBills::class)
    ->dailyAt('06:55')
    ->withoutOverlapping()
    ->onOneServer();

// Pengingat tagihan yang mendekati / melewati jatuh tempo.
Schedule::command(RemindDueBills::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Jaring pengaman untuk anggaran yang menembus 70% / 100%
// (pengecekan realtime tetap jalan lewat job setiap kali transaksi dibuat).
Schedule::command(CheckBudgetThresholds::class)
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->onOneServer();

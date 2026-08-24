<?php

namespace App\Console\Commands;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\User;
use App\Notifications\BillDueReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Berjalan setiap hari lewat Task Scheduling.
 * Mengirim push notification untuk tagihan yang mendekati / melewati jatuh tempo.
 */
class RemindDueBills extends Command
{
    protected $signature = 'bills:remind {--date= : Tanggal acuan (Y-m-d), default hari ini}';

    protected $description = 'Kirim pengingat untuk tagihan yang mendekati jatuh tempo';

    public function handle(): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $sent = 0;

        Bill::query()
            ->where('status', BillStatus::Unpaid->value)
            // Prefilter kasar di database (portabel MySQL/SQLite); jendela pengingat
            // per tagihan (remind_days_before) dicek setelah baris diambil.
            ->whereDate('due_date', '<=', $today->addDays(30)->toDateString())
            // Belum diingatkan hari ini (idempoten kalau scheduler jalan berulang).
            ->where(function ($query) use ($today): void {
                $query->whereNull('reminded_on')
                    ->orWhereDate('reminded_on', '<', $today->toDateString());
            })
            ->chunkById(200, function (Collection $bills) use ($today, &$sent): void {
                $users = User::query()
                    ->whereIn('id', $bills->pluck('user_id')->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($bills as $bill) {
                    $daysLeft = (int) $today->diffInDays(CarbonImmutable::parse($bill->due_date), false);

                    if ($daysLeft > $bill->remind_days_before) {
                        continue; // Masih jauh dari jatuh tempo.
                    }

                    $user = $users->get($bill->user_id);

                    if (! $user instanceof User) {
                        continue;
                    }

                    $user->notify(BillDueReminder::forBill($bill, $daysLeft));

                    $bill->forceFill(['reminded_on' => $today->toDateString()])->save();
                    $sent++;
                }
            });

        $this->info("Pengingat tagihan terkirim: {$sent}");

        return self::SUCCESS;
    }
}

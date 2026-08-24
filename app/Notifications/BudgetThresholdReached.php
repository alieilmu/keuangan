<?php

namespace App\Notifications;

use App\Support\BudgetStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BudgetThresholdReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $categoryName,
        public readonly float $spent,
        public readonly float $limit,
        public readonly float $percentage,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'budget',
            'title' => $this->title(),
            'body' => $this->body(),
            'category' => $this->categoryName,
            'percentage' => $this->percentage,
            'spent' => $this->spent,
            'limit' => $this->limit,
            'status' => BudgetStatus::fromPercentage($this->percentage),
            'url' => '/budgets',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('budget-'.md5($this->categoryName))
            ->data(['url' => '/budgets'])
            ->options(['TTL' => 3600]);
    }

    private function title(): string
    {
        return $this->percentage > 100
            ? 'Anggaran '.$this->categoryName.' overbudget'
            : 'Anggaran '.$this->categoryName.' sudah '.rtrim(rtrim(number_format($this->percentage, 1, ',', '.'), '0'), ',').'%';
    }

    private function body(): string
    {
        return sprintf(
            'Terpakai Rp %s dari limit Rp %s bulan ini.',
            number_format($this->spent, 0, ',', '.'),
            number_format($this->limit, 0, ',', '.')
        );
    }
}

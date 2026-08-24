<?php

namespace App\Notifications;

use App\Models\Bill;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BillDueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $billId,
        public readonly string $title,
        public readonly float $amount,
        public readonly string $dueDate,
        public readonly int $daysLeft,
    ) {}

    public static function forBill(Bill $bill, int $daysLeft): self
    {
        return new self(
            (int) $bill->getKey(),
            $bill->title,
            (float) $bill->amount,
            $bill->due_date->toDateString(),
            $daysLeft,
        );
    }

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
            'type' => 'bill',
            'title' => $this->headline(),
            'body' => $this->body(),
            'bill_id' => $this->billId,
            'amount' => $this->amount,
            'due_date' => $this->dueDate,
            'days_left' => $this->daysLeft,
            'url' => '/bills',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->headline())
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('bill-'.$this->billId)
            ->requireInteraction()
            ->action('Bayar Sekarang', 'pay')
            ->data(['url' => '/bills', 'bill_id' => $this->billId])
            ->options(['TTL' => 86400]);
    }

    private function headline(): string
    {
        return match (true) {
            $this->daysLeft < 0 => 'Tagihan '.$this->title.' terlambat',
            $this->daysLeft === 0 => 'Tagihan '.$this->title.' jatuh tempo hari ini',
            default => 'Tagihan '.$this->title.' jatuh tempo '.$this->daysLeft.' hari lagi',
        };
    }

    private function body(): string
    {
        return sprintf('Rp %s - jatuh tempo %s.',
            number_format($this->amount, 0, ',', '.'),
            CarbonImmutable::parse($this->dueDate)->translatedFormat('d M Y')
        );
    }
}

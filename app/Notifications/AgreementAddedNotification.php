<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a dealer adds a new agreement to an account that already exists,
 * rather than to a brand-new customer. A fresh account gets the usual "verify
 * your email" link. An existing one gets this instead, so a mistyped email
 * can't quietly attach someone else's car and Direct Debit to your account.
 * It spells out what was added and tells you what to do if it wasn't you.
 */
class AgreementAddedNotification extends Notification
{
    use Queueable;

    public function __construct(public Agreement $agreement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registration = $this->agreement->vehicle?->registration ?? 'your vehicle';
        $monthly = number_format((float) $this->agreement->monthly_price, 2);

        return (new MailMessage)
            ->subject('A car was added to your Warranty Wise account')
            ->greeting("Hello {$notifiable->name}")
            ->line('A new warranty agreement has just been set up on your existing account.')
            ->line("Agreement {$this->formatNumber()} covers {$registration}, with a monthly Direct Debit of £{$monthly}.")
            ->line('If you arranged this with your dealer, there is nothing to do. It will show up in the app next time you sign in.')
            ->line('If you were not expecting this, or it was not you, contact us straight away and we will put it right.');
    }

    /**
     * The agreement number the apps show, e.g. "WW-4471-228901" from the bare
     * 10 digits we store.
     */
    private function formatNumber(): string
    {
        $digits = $this->agreement->agreement_number;

        return 'WW-'.substr($digits, 0, 4).'-'.substr($digits, 4);
    }
}

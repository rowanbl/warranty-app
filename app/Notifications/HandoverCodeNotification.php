<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a customer once a dealer has set their account up. They use the WW ID
 * and code to claim it.
 */
class HandoverCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $wwId, public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Warranty Wise account is ready')
            ->greeting('Your account is set up')
            ->line("Your dealer has set everything up for you. Open the app, enter your WW ID {$this->wwId}, then this code to claim your account.")
            ->line("Your code: {$this->code}")
            ->line('The code expires in 7 days.');
    }
}

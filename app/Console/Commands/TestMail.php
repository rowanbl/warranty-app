<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('ww:test-mail')]
#[Description('Send a test email to check the mailer works, and print the real error if it fails.')]
class TestMail extends Command
{
    #[Argument('Where to send the test email')]
    public string $email;

    public function handle(): int
    {
        try {
            Mail::raw('This is a Warranty Wise test email. If you got this, mail works.', function ($message) {
                $message->to($this->email)->subject('Warranty Wise test email');
            });
        } catch (\Throwable $e) {
            $this->error('Mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent a test email to {$this->email} via ".config('mail.default').'.');

        return self::SUCCESS;
    }
}

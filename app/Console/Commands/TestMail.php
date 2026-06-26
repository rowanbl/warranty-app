<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'ww:test-mail {email}';

    protected $description = 'Send a test email to check the mailer works, and print the real error if it fails.';

    public function handle(): int
    {
        $email = $this->argument('email');

        try {
            Mail::raw('This is a Warranty Wise test email. If you got this, mail works.', function ($message) use ($email) {
                $message->to($email)->subject('Warranty Wise test email');
            });
        } catch (\Throwable $e) {
            $this->error('Mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent a test email to {$email} via ".config('mail.default').'.');

        return self::SUCCESS;
    }
}

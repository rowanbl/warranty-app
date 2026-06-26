<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ApproveAccount extends Command
{
    protected $signature = 'ww:approve {email}';

    protected $description = 'Approve a dealer or garage account so they can sign in.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::whereEmail($email)->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $profile = $user->profile();

        if (! $user->needsApproval() || $profile === null) {
            $this->error("{$email} is a {$user->account_type->value} account, which doesn't need approval.");

            return self::FAILURE;
        }

        $profile->update(['approved_at' => now()]);
        $this->info("Approved {$email}. They can sign in now.");

        return self::SUCCESS;
    }
}

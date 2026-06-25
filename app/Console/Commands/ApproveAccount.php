<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ww:approve')]
#[Description('Approve a dealer or garage account so they can sign in.')]
class ApproveAccount extends Command
{
    #[Argument('The account email to approve')]
    public string $email;

    public function handle(): int
    {
        $user = User::whereEmail($this->email)->first();

        if (! $user) {
            $this->error("No account found for {$this->email}.");

            return self::FAILURE;
        }

        $profile = $user->profile();

        if (! $user->needsApproval() || $profile === null) {
            $this->error("{$this->email} is a {$user->account_type->value} account, which doesn't need approval.");

            return self::FAILURE;
        }

        $profile->update(['approved_at' => now()]);
        $this->info("Approved {$this->email}. They can sign in now.");

        return self::SUCCESS;
    }
}

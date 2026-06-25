<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code emailed to a customer so they can sign in without a password.
 * Stored as a hash, valid for a short window, and good for a single use.
 */
class EmailLoginCode extends Model
{
    protected $fillable = ['email', 'code_hash', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}

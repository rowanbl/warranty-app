<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDetail extends Model
{
    protected $guarded = ['id'];

    // Sort code and account number are encrypted at rest. The model hides them
    // so they never leak into a JSON response by accident.
    protected $hidden = ['sort_code', 'account_number'];

    protected function casts(): array
    {
        return [
            'sort_code' => 'encrypted',
            'account_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Handover extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cover' => 'array',
            'monthly_price' => 'decimal:2',
            'commission' => 'decimal:2',
            'claimed_at' => 'datetime',
        ];
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dealer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}

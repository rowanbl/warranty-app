<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Dealer profile. The type-specific half of a dealer account.
class Dealer extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['id', 'user_id', 'created_at', 'updated_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

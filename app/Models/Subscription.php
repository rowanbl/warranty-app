<?php

namespace App\Models;

use App\Enums\SubscriptionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One add-on subscription on an agreement. Stopping sets `ended_at`; restarting
 * is a fresh row, so the table is a full history of when each cover started and
 * stopped.
 */
class Subscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => SubscriptionType::class,
            'monthly_price' => 'decimal:2',
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @param  Builder<Subscription>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}

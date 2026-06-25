<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'engine_capacity' => 'integer',
            'mileage' => 'integer',
            'mot_due' => 'date',
            'tax_due' => 'date',
            'insurance_renewal' => 'date',
            'last_service' => 'date',
            'service_due' => 'date',
            'mot_history' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }
}

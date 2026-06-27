<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// A forecourt from the Fuel Finder feed, with its latest prices and the location
// we geocoded for it. Ingested on a schedule, then queried for "near me" search.
class FuelStation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'prices' => 'array',
            'geocoded_at' => 'datetime',
            'prices_updated_at' => 'datetime',
            'geocode_failed' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\WarrantyTier;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A warranty agreement, kept separate from the account so one account can hold
// several. Each one covers a vehicle.
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tier' => WarrantyTier::class,
            'start_date' => 'date',
            'expiry_date' => 'date',
            'claim_limit' => 'integer',
            'monthly_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

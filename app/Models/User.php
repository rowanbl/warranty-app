<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'account_type', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_primary', true);
    }

    /**
     * Store an address for this user, reusing an identical one already on file
     * rather than duplicating it. The first address a user gets becomes their
     * main one.
     *
     * @param  array<string, mixed>  $fields
     */
    public function rememberAddress(array $fields): Address
    {
        $address = $this->addresses()->firstOrCreate(
            ['line1' => $fields['line1'], 'postcode' => $fields['postcode']],
            $fields,
        );

        if (! $this->addresses()->where('is_primary', true)->exists()) {
            $address->update(['is_primary' => true]);
        }

        return $address;
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function dealer(): HasOne
    {
        return $this->hasOne(Dealer::class);
    }

    public function garage(): HasOne
    {
        return $this->hasOne(Garage::class);
    }

    /**
     * The type-specific profile for this account, or null for accounts that
     * don't have one (admin). account_type decides which table to read.
     */
    public function profile(): ?Model
    {
        return match ($this->account_type) {
            AccountType::Customer => $this->customer,
            AccountType::Dealer => $this->dealer,
            AccountType::Garage => $this->garage,
            AccountType::Admin => null,
        };
    }

    /**
     * Dealers and garages have to be approved by a human before they can use
     * the app. Everyone else is fine once their email is verified.
     */
    public function needsApproval(): bool
    {
        return in_array($this->account_type, [AccountType::Dealer, AccountType::Garage], true);
    }

    public function isApproved(): bool
    {
        if (! $this->needsApproval()) {
            return true;
        }

        return $this->profile()?->isApproved() ?? false;
    }
}

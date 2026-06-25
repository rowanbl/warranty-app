<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shape of a user as the clients see it. account_type tells the app which
 * home to show after sign-in. Dates go out as plain strings the clients parse.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_type' => $this->account_type->value,
            'email_verified' => $this->hasVerifiedEmail(),
            // The type-specific bits (phone, address, business name) live here,
            // joined from the matching profile table.
            'profile' => $this->profile(),
        ];
    }
}

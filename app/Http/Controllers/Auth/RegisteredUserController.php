<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    /**
     * Register a password account. Mostly dealers, garages and staff. The user
     * has to verify their email before they can sign in, so no token yet.
     */
    public function store(Request $request): JsonResponse
    {
        $selfRegisterable = array_map(fn (AccountType $type) => $type->value, AccountType::selfRegisterable());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'account_type' => ['nullable', Rule::in($selfRegisterable)],
            'business_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            // Structured address (optional). Sending any of it requires at least
            // the line and postcode, so what we store is usable.
            'address' => ['nullable', 'array'],
            'address.label' => ['nullable', 'string', 'max:50'],
            'address.line1' => ['required_with:address', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.county' => ['nullable', 'string', 'max:255'],
            'address.postcode' => ['required_with:address', 'string', 'max:12'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $type = AccountType::from($validated['account_type'] ?? AccountType::Customer->value);

        $user = DB::transaction(function () use ($validated, $type) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'account_type' => $type,
                'password' => $validated['password'],
            ]);

            $this->createProfile($user, $type, $validated);

            if (! empty($validated['address'])) {
                $user->rememberAddress($validated['address']);
            }

            return $user;
        });

        event(new Registered($user));

        return response()->json([
            'message' => 'Check your email to verify your account.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Create the profile row that matches the account type. The type-specific
     * fields land here, not on the user.
     *
     * @param  array<string, mixed>  $input
     */
    private function createProfile(User $user, AccountType $type, array $input): void
    {
        $businessName = $input['business_name'] ?? $input['name'];

        match ($type) {
            AccountType::Customer => $user->customer()->create([
                'phone' => $input['phone'] ?? null,
            ]),
            AccountType::Dealer => $user->dealer()->create([
                'business_name' => $businessName,
                'phone' => $input['phone'] ?? null,
            ]),
            AccountType::Garage => $user->garage()->create([
                'business_name' => $businessName,
                'phone' => $input['phone'] ?? null,
            ]),
            AccountType::Admin => null,
        };
    }
}

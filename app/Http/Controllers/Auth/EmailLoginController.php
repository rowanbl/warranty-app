<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\EmailLoginCode;
use App\Models\User;
use App\Notifications\EmailLoginCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless sign-in for customers. They ask for a code, we email it, they
 * enter it. This is the usual way customers get in. It's a login, not a way to
 * verify an email, so the account must already be verified (via the magic link
 * sent at registration) before a code is any use.
 */
class EmailLoginController extends Controller
{
    /**
     * Email a fresh sign-in code. Always answers the same way so the response
     * never reveals whether an email is registered. Only verified accounts get
     * a code, since unverified accounts can't sign in yet anyway.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = Str::lower($validated['email']);
        $user = User::where('email', $email)->first();

        if ($user && $user->hasVerifiedEmail()) {
            // One live code per email keeps the verify step simple.
            EmailLoginCode::where('email', $email)->delete();

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            EmailLoginCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
            ]);

            $user->notify(new EmailLoginCodeNotification($code));
        }

        return response()->json([
            'message' => 'If that email is registered, a code is on its way.',
        ]);
    }

    /**
     * Check the code and sign the customer in. The account must be verified
     * first, so a code only ever exists for a user who can already log in.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ]);

        $email = Str::lower($validated['email']);

        $record = EmailLoginCode::where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record || ! Hash::check($validated['code'], $record->code_hash)) {
            throw ValidationException::withMessages([
                'code' => 'That code is wrong or has expired.',
            ]);
        }

        $user = User::where('email', $email)->firstOrFail();

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verify your email address before signing in.',
            ], 403);
        }

        $record->update(['consumed_at' => now()]);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}

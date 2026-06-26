<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Models\Agreement;
use App\Models\EmailLoginCode;
use App\Models\User;
use App\Notifications\EmailLoginCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless sign-in by agreement number. The customer enters their number; if
 * the email's verified we email a one-time code to sign in with. If it isn't
 * verified yet, the app is told so it can offer to resend the verification link.
 * The device then remembers them via the local PIN / Face ID, so this only runs
 * on a new device or after signing out.
 */
class AgreementLoginController extends Controller
{
    /**
     * Step one: enter the agreement number. Email a sign-in code when verified,
     * report "not verified" so the app can offer a resend, or 404 if unknown.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agreement_number' => ['required', 'string'],
        ]);

        $user = $this->userFor($validated['agreement_number']);

        if ($user === null) {
            return response()->json(['message' => 'We couldn\'t find that agreement number.'], 404);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'verified' => false,
                'message' => 'Your email isn\'t verified yet. Check your inbox for the link.',
            ], 409);
        }

        EmailLoginCode::where('email', $user->email)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        EmailLoginCode::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $user->notify(new EmailLoginCodeNotification($code));

        return response()->json(['verified' => true]);
    }

    /**
     * Step two: the emailed code signs them in.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agreement_number' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $user = $this->userFor($validated['agreement_number']);

        $record = $user
            ? EmailLoginCode::where('email', $user->email)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest()
                ->first()
            : null;

        if ($user === null || $record === null || ! Hash::check($validated['code'], $record->code_hash)) {
            throw ValidationException::withMessages([
                'code' => 'That agreement number and code don\'t match.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email before signing in.'], 403);
        }

        $record->update(['consumed_at' => now()]);
        $user->load('vehicles');

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
            'vehicles' => VehicleResource::collection($user->vehicles),
        ]);
    }

    /**
     * Resend the verification magic link for an unverified account.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agreement_number' => ['required', 'string'],
        ]);

        $user = $this->userFor($validated['agreement_number']);

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If that account still needs verifying, a link is on its way.']);
    }

    /**
     * Resolve an agreement number (however it's typed) to the customer it belongs
     * to. Strips the "WW", dashes and spaces down to the bare digits we stored.
     */
    private function userFor(string $agreementNumber): ?User
    {
        $number = preg_replace('/\D/', '', $agreementNumber) ?? '';

        return Agreement::where('agreement_number', $number)->first()?->user;
    }
}

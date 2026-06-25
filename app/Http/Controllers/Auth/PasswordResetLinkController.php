<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetLinkController extends Controller
{
    /**
     * Email a password reset link. Always answers the same way so it never
     * reveals whether an email is registered.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink(['email' => Str::lower($request->input('email'))]);

        return response()->json([
            'message' => 'If that email is registered, a reset link is on its way.',
        ]);
    }
}

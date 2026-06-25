<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a signed-in dealer or garage from doing anything in the app until a
 * human has approved them. UI gating isn't enough, the token must be powerless
 * until approval. /me and /logout stay open so the app can poll and sign out.
 */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isApproved()) {
            return response()->json([
                'message' => 'Your account is awaiting approval.',
            ], 403);
        }

        return $next($request);
    }
}

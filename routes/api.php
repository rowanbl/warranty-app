<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailLoginController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['message' => 'Warranty app api']);

// Public auth. Password sign-up and sign-in for dealers, garages and staff,
// plus the passwordless email-code route customers usually take.
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

Route::post('/login/email/request', [EmailLoginController::class, 'request'])->middleware('throttle:email-code');
Route::post('/login/email/verify', [EmailLoginController::class, 'verify']);

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

// A customer claims the account their dealer prepared. Public: they're not
// signed in yet, the WW ID and code are what prove who they are.
Route::post('/handovers/redeem', [HandoverController::class, 'redeem']);

// Preview a reg during onboarding, before there's an account. Public but
// throttled, since each call hits the paid DVSA/DVLA lookup.
Route::post('/vehicles/preview', [VehicleLookupController::class, 'preview'])->middleware('throttle:20,1');

// Signed in. A valid bearer token is required.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    // Verified only. The rest of the app hangs off here.
    Route::middleware('verified')->group(function () {
        Route::get('/me', [AuthenticatedSessionController::class, 'current']);

        // Look a reg up for real and save it to the account.
        Route::post('/vehicles/lookup', [VehicleLookupController::class, 'store']);

        // Dealer sets a customer's account up.
        Route::post('/handovers', [HandoverController::class, 'store']);
    });
});

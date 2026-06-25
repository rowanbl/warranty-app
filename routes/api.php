<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailLoginController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DemoContentController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\VehicleLookupController;
use App\Http\Controllers\WarrantyController;
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
Route::post('/handovers/check', [HandoverController::class, 'check'])->middleware('throttle:20,1');
Route::post('/handovers/redeem', [HandoverController::class, 'redeem']);

// Preview a reg during onboarding, before there's an account. Public but
// throttled, since each call hits the paid DVSA/DVLA lookup.
Route::post('/vehicles/preview', [VehicleLookupController::class, 'preview'])->middleware('throttle:20,1');

// Price the warranty for a car. Public + throttled (it does a paid reg lookup).
Route::post('/warranty/quote', [WarrantyController::class, 'quote'])->middleware('throttle:20,1');

// App content. Demo data for now, served from here so the app goes live by
// updating these endpoints, not by changing the app. Public while it's demo.
Route::get('/reminders', [DemoContentController::class, 'reminders']);
Route::get('/cover-options', [DemoContentController::class, 'coverOptions']);
Route::get('/service-prices', [DemoContentController::class, 'servicePrices']);
Route::get('/symptoms', [DemoContentController::class, 'symptoms']);
Route::get('/tools', [DemoContentController::class, 'tools']);
Route::post('/diagnosis', [DemoContentController::class, 'diagnosis']);
Route::post('/sanity-check', [DemoContentController::class, 'sanityCheck']);
Route::post('/bookings', [DemoContentController::class, 'createBooking']);
Route::get('/repair-timeline', [DemoContentController::class, 'repairTimeline']);
Route::get('/fuel-stations', [DemoContentController::class, 'fuelStations']);
Route::get('/warranty', [DemoContentController::class, 'warranty']);
Route::get('/admin/kpis', [DemoContentController::class, 'kpis']);
Route::get('/admin/claims', [DemoContentController::class, 'claims']);

// Signed in. A valid bearer token is required.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    // Verified only. /me stays open so an awaiting dealer can poll their status.
    Route::middleware('verified')->group(function () {
        Route::get('/me', [AuthenticatedSessionController::class, 'current']);

        // Approved only. Unapproved dealers/garages get a 403, so the token is
        // powerless until a human approves them.
        Route::middleware('approved')->group(function () {
            // Look a reg up for real and save it to the account.
            Route::post('/vehicles/lookup', [VehicleLookupController::class, 'store']);

            // Dealer sets a customer's account up.
            Route::post('/handovers', [HandoverController::class, 'store']);
        });
    });
});

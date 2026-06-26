<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHandoverRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Models\Handover;
use App\Services\Handover\HandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HandoverController extends Controller
{
    public function __construct(private HandoverService $service) {}

    /**
     * Dealer side. Set a customer's whole account up and return the WW ID to
     * read out to them. The matching code is emailed to the customer.
     */
    public function store(StoreHandoverRequest $request): JsonResponse
    {
        $handover = $this->service->submit($request->user(), $request->validated());

        // The code is emailed to the customer, never returned here.
        return response()->json([
            'ww_id' => $handover->ww_id,
            'customer' => new UserResource($handover->customer),
        ], 201);
    }

    /**
     * The customer enters their agreement number (WW ID) to log in. Works the
     * first time and every time after, so it's a real passwordless login, not a
     * one-time claim. If it's a real number we email a fresh code; unknown
     * numbers are rejected here.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ww_id' => ['required', 'string'],
        ]);

        $wwId = preg_replace('/\D/', '', $validated['ww_id']) ?? '';

        $handover = Handover::where('ww_id', $wwId)->first();

        if ($handover === null) {
            return response()->json([
                'message' => 'We couldn\'t find that agreement number.',
            ], 404);
        }

        $this->service->sendCode($handover);

        return response()->json(['exists' => true]);
    }

    /**
     * Customer side. Claim a prepared account with the WW ID and code, and get
     * signed in to the account the dealer already filled out.
     */
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ww_id' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $handover = $this->service->redeem($validated['ww_id'], $validated['code']);
        $customer = $handover->customer->load('vehicles');

        return response()->json([
            'token' => $customer->createToken('api')->plainTextToken,
            'user' => new UserResource($customer),
            'vehicles' => VehicleResource::collection($customer->vehicles),
            'cover' => $handover->cover,
            'handover' => [
                'ww_id' => $handover->ww_id,
                'monthly_price' => $handover->monthly_price,
                'commission' => $handover->commission,
            ],
        ]);
    }
}

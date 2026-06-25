<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHandoverRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
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
        $result = $this->service->submit($request->user(), $request->validated());

        $payload = [
            'ww_id' => $result->handover->ww_id,
            'customer' => new UserResource($result->handover->customer),
        ];

        // Handy for demos so you don't have to dig the code out of the email.
        // Never exposed in production.
        if (config('app.debug')) {
            $payload['demo_code'] = $result->code;
        }

        return response()->json($payload, 201);
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

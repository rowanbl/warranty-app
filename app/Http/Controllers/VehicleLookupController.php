<?php

namespace App\Http\Controllers;

use App\Http\Resources\VehicleResource;
use App\Services\Vehicle\VehicleLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleLookupController extends Controller
{
    /**
     * Look a registration up for real and save the car to the signed-in user's
     * account. Used during self-onboard.
     */
    public function store(Request $request, VehicleLookupService $lookup): JsonResponse
    {
        $validated = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
        ]);

        $data = $lookup->lookup($validated['registration']);

        if ($data === null) {
            return response()->json([
                'message' => 'We couldn\'t find a vehicle for that registration.',
            ], 404);
        }

        $vehicle = $request->user()->vehicles()->updateOrCreate(
            ['registration' => $data->registration],
            $data->toArray(),
        );

        return response()->json([
            'vehicle' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Look a registration up without signing in or saving anything. Used during
     * onboarding to preview the car before there's an account to attach it to.
     * Public, so it's throttled to keep the paid lookup from being abused.
     */
    public function preview(Request $request, VehicleLookupService $lookup): JsonResponse
    {
        $validated = $request->validate([
            'registration' => ['required', 'string', 'max:10'],
        ]);

        $data = $lookup->lookup($validated['registration']);

        if ($data === null) {
            return response()->json([
                'message' => 'We couldn\'t find a vehicle for that registration.',
            ], 404);
        }

        return response()->json([
            'vehicle' => $data->toArray(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\DemoContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the app's content. Demo data for now, served from the server so going
 * live is a matter of replacing each method's body with the real source. The
 * JSON shape matches what the app already decodes.
 */
class DemoContentController extends Controller
{
    public function reminders(): JsonResponse
    {
        return response()->json(DemoContent::get('reminders'));
    }

    public function coverOptions(): JsonResponse
    {
        return response()->json(DemoContent::get('coverOptions'));
    }

    public function servicePrices(): JsonResponse
    {
        return response()->json(DemoContent::get('servicePrices'));
    }

    public function symptoms(): JsonResponse
    {
        return response()->json(DemoContent::get('symptoms'));
    }

    public function tools(): JsonResponse
    {
        return response()->json(DemoContent::get('tools'));
    }

    public function diagnosis(Request $request): JsonResponse
    {
        return response()->json(DemoContent::get('diagnosis'));
    }

    public function sanityCheck(Request $request): JsonResponse
    {
        return response()->json(DemoContent::get('sanity'));
    }

    public function repairTimeline(): JsonResponse
    {
        return response()->json(DemoContent::get('trackSteps'));
    }

    public function fuelStations(): JsonResponse
    {
        return response()->json(DemoContent::get('fuelStations'));
    }

    public function kpis(): JsonResponse
    {
        return response()->json(DemoContent::get('kpis'));
    }

    public function claims(): JsonResponse
    {
        return response()->json(DemoContent::get('claims'));
    }

    public function createBooking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'string'],
            'time' => ['nullable', 'string'],
        ]);

        return response()->json([
            'reference' => 'SS-'.random_int(10000, 99999),
            'repairer' => 'Pinpoint Autos, Burnley',
            'message' => 'Collection booked for '.($validated['date'] ?? 'your chosen date').', '.($validated['time'] ?? '').'.',
        ]);
    }
}

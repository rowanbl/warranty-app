<?php

namespace App\Http\Controllers;

use App\Services\Address\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Address autocomplete for any form that needs one, users picking their own
 * address or dealers entering a customer's. Search drives the type-ahead;
 * retrieve turns the chosen suggestion into a full address with coordinates.
 */
class AddressController extends Controller
{
    public function search(Request $request, AddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:255'],
            'container' => ['nullable', 'string', 'max:255'],
        ]);

        $suggestions = $addresses->search($validated['text'], $validated['container'] ?? null);

        return response()->json($suggestions);
    }

    public function retrieve(Request $request, AddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
        ]);

        $address = $addresses->retrieve($validated['id']);

        if ($address === null) {
            return response()->json(['message' => 'That address could not be found.'], 404);
        }

        return response()->json($address);
    }
}

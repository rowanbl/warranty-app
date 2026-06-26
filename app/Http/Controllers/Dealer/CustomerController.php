<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Resources\UserResource;
use App\Services\Dealer\DealerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(private DealerService $service) {}

    /**
     * A dealer registers a customer's whole account. Returns the agreement number
     * to read out to them; the customer gets a verification email and signs in
     * with that number once verified.
     */
    public function store(RegisterCustomerRequest $request): JsonResponse
    {
        $agreement = $this->service->registerCustomer($request->validated());

        return response()->json([
            'agreement_number' => $agreement->agreement_number,
            'customer' => new UserResource($agreement->user),
        ], 201);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A car as the clients see it. Dates go out as plain yyyy-mm-dd strings.
 *
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration' => $this->registration,
            'make' => $this->make,
            'model' => $this->model,
            'derivative' => $this->derivative,
            'year' => $this->year,
            'fuel' => $this->fuel,
            'colour' => $this->colour,
            'engine_capacity' => $this->engine_capacity,
            'mileage' => $this->mileage,
            'mot_due' => $this->mot_due?->toDateString(),
            'tax_due' => $this->tax_due?->toDateString(),
            'insurance_renewal' => $this->insurance_renewal?->toDateString(),
            'last_service' => $this->last_service?->toDateString(),
            'service_due' => $this->service_due?->toDateString(),
            'mot_history' => $this->mot_history ?? [],
        ];
    }
}

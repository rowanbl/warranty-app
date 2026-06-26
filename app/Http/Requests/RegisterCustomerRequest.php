<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCustomerRequest extends FormRequest
{
    /**
     * Only dealers, garages and staff register customers.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->account_type, [
            AccountType::Dealer,
            AccountType::Garage,
            AccountType::Admin,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.address' => ['nullable', 'string', 'max:255'],

            'vehicle' => ['required', 'array'],
            'vehicle.registration' => ['required', 'string', 'max:10'],
            'vehicle.mileage' => ['nullable', 'integer', 'min:0'],
            'vehicle.insurance_renewal' => ['nullable', 'date'],
            'vehicle.last_service' => ['nullable', 'date'],

            // The warranty is the product, and it becomes the customer's agreement
            // (and login number), so it's required.
            'warranty' => ['required', 'array'],
            'warranty.term_months' => ['required', 'integer', 'min:1'],
            'warranty.monthly' => ['required', 'numeric', 'min:0'],

            'bank' => ['required', 'array'],
            'bank.account_name' => ['required', 'string', 'max:255'],
            'bank.sort_code' => ['required', 'string', 'max:10'],
            'bank.account_number' => ['required', 'string', 'max:20'],
        ];
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarrantyQuoteTest extends TestCase
{
    // A 2020 BMW with 30k miles → comfortably inside the plans.
    private function fakeLookup(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
            'history.mot.api.gov.uk/*' => Http::response([
                'registration' => 'LV68KXR', 'make' => 'BMW', 'model' => '3 Series',
                'motTests' => [
                    ['completedDate' => '2025-09-12', 'testResult' => 'PASSED', 'odometerValue' => '30000', 'expiryDate' => '2026-09-12'],
                ],
            ]),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW', 'yearOfManufacture' => 2020]),
        ]);
    }

    public function test_a_quote_pulls_the_car_make_and_model(): void
    {
        $this->fakeLookup();

        $this->postJson('/api/warranty/quote', ['registration' => 'LV68 KXR'])
            ->assertOk()
            ->assertJsonPath('make', 'BMW')
            ->assertJsonPath('model', '3 Series');
    }

    public function test_an_eligible_car_returns_a_plan_and_terms(): void
    {
        $this->fakeLookup();

        $this->postJson('/api/warranty/quote', ['registration' => 'LV68KXR'])
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('tier', '06/60')
            ->assertJsonStructure(['terms' => [['months', 'monthly', 'upfront', 'upfrontSaving']]]);
    }

    public function test_a_car_outside_the_plans_is_not_eligible(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
            'history.mot.api.gov.uk/*' => Http::response([
                'registration' => 'OLD1', 'make' => 'BMW',
                'motTests' => [['completedDate' => '2025-01-01', 'testResult' => 'PASSED', 'odometerValue' => '200000']],
            ]),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW', 'yearOfManufacture' => 2005]),
        ]);

        $this->postJson('/api/warranty/quote', ['registration' => 'OLD1'])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonMissingPath('terms');
    }

    public function test_mileage_pushes_the_car_into_a_bigger_plan(): void
    {
        $this->fakeLookup();

        // Same 2020 car, but 85k miles entered by the dealer → bumped to 10/100.
        $this->postJson('/api/warranty/quote', ['registration' => 'LV68KXR', 'mileage' => 85000])
            ->assertOk()
            ->assertJsonPath('tier', '10/100');
    }

    public function test_longer_terms_are_cheaper_per_month(): void
    {
        $this->fakeLookup();

        $terms = collect($this->postJson('/api/warranty/quote', ['registration' => 'LV68KXR'])->json('terms'));

        $shortest = $terms->firstWhere('months', 12)['monthly'];
        $longest = $terms->firstWhere('months', 60)['monthly'];

        $this->assertLessThan($shortest, $longest);
    }

    public function test_paying_upfront_is_discounted(): void
    {
        $this->fakeLookup();

        $term = collect($this->postJson('/api/warranty/quote', ['registration' => 'LV68KXR'])->json('terms'))
            ->firstWhere('months', 24);

        // Upfront is the full term minus the saving, so it's less than monthly x months.
        $this->assertLessThan($term['monthly'] * 24, $term['upfront']);
        $this->assertGreaterThan(0, $term['upfrontSaving']);
    }
}

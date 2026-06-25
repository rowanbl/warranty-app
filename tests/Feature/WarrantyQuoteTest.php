<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarrantyQuoteTest extends TestCase
{
    private function fakeLookup(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token']),
            'history.mot.api.gov.uk/*' => Http::response([
                'registration' => 'LV68KXR', 'make' => 'BMW', 'model' => '3 Series',
            ]),
            'driver-vehicle-licensing.api.gov.uk/*' => Http::response(['make' => 'BMW']),
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

    public function test_a_quote_returns_term_options(): void
    {
        $this->fakeLookup();

        $this->postJson('/api/warranty/quote', ['registration' => 'LV68KXR'])
            ->assertOk()
            ->assertJsonStructure(['terms' => [['months', 'monthly', 'upfront', 'upfrontSaving']]]);
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

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressLookupTest extends TestCase
{
    public function test_search_returns_suggestions(): void
    {
        Http::fake([
            'api.addressy.com/Capture/Interactive/Find/*' => Http::response([
                'Items' => [
                    ['Id' => 'GB|RM|A|1', 'Type' => 'Address', 'Text' => '1 High St', 'Description' => 'Burnley, BB11'],
                    ['Id' => 'GB|RM|ENG|BB11', 'Type' => 'Postcode', 'Text' => 'BB11', 'Description' => '120 addresses'],
                ],
            ]),
        ]);

        $this->getJson('/api/address/search?text=1 High St')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.type', 'Address')
            ->assertJsonPath('0.id', 'GB|RM|A|1');
    }

    public function test_search_needs_some_text(): void
    {
        $this->getJson('/api/address/search')->assertStatus(422);
    }

    public function test_retrieve_returns_the_full_address_with_coordinates(): void
    {
        Http::fake([
            'api.addressy.com/Capture/Interactive/Retrieve/*' => Http::response([
                'Items' => [['Line1' => '1 High St', 'Line2' => '', 'City' => 'Burnley', 'PostalCode' => 'BB11 1BD']],
            ]),
            'api.addressy.com/Geocoding/UK/*' => Http::response([
                'Items' => [['Latitude' => 53.789, 'Longitude' => -2.245]],
            ]),
        ]);

        $this->getJson('/api/address/retrieve?id=GB|RM|A|1')
            ->assertOk()
            ->assertJsonPath('postcode', 'BB11 1BD')
            ->assertJsonPath('city', 'Burnley')
            ->assertJsonPath('latitude', 53.789);
    }

    public function test_retrieve_is_a_404_when_loqate_has_nothing(): void
    {
        Http::fake([
            'api.addressy.com/Capture/Interactive/Retrieve/*' => Http::response(['Items' => []]),
        ]);

        $this->getJson('/api/address/retrieve?id=nope')->assertNotFound();
    }
}

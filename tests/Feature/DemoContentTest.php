<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoContentTest extends TestCase
{
    public function test_reminders_come_back_in_the_app_shape(): void
    {
        $response = $this->getJson('/api/reminders');

        $response->assertOk()
            ->assertJsonStructure([['kind', 'title', 'detail']]);
    }

    public function test_cover_options_come_back(): void
    {
        $this->getJson('/api/cover-options')
            ->assertOk()
            ->assertJsonStructure([['name', 'price', 'period', 'features', 'icon']]);
    }

    public function test_diagnosis_returns_a_single_result(): void
    {
        $this->postJson('/api/diagnosis', [])
            ->assertOk()
            ->assertJsonStructure(['likelyFault', 'severity', 'severityColor', 'confidence']);
    }

    public function test_warranty_returns_the_agreement(): void
    {
        $this->getJson('/api/warranty')
            ->assertOk()
            ->assertJsonStructure(['agreementNumber', 'tier', 'startDate', 'expiryDate']);
    }

    public function test_booking_echoes_a_confirmation(): void
    {
        $this->postJson('/api/bookings', ['date' => 'Tue 7 Jul', 'time' => '08:00 to 10:00'])
            ->assertOk()
            ->assertJsonStructure(['reference', 'repairer', 'message']);
    }

    public function test_admin_kpis_and_claims_come_back(): void
    {
        $this->getJson('/api/admin/kpis')->assertOk()->assertJsonStructure([['value', 'label', 'tint']]);
        $this->getJson('/api/admin/claims')->assertOk()->assertJsonStructure([['ref', 'customer', 'status']]);
    }
}

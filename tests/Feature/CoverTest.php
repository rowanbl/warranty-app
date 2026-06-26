<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithAgreement(): array
    {
        $user = User::factory()->create();
        $agreement = Agreement::factory()->create(['user_id' => $user->id]);

        return [$user, $agreement];
    }

    public function test_cover_lists_the_catalogue_with_active_flags(): void
    {
        [$user] = $this->customerWithAgreement();

        $this->actingAs($user)->getJson('/api/cover')
            ->assertOk()
            ->assertJsonStructure(['cover' => [['name', 'price', 'type', 'active']]])
            ->assertJsonPath('cover.0.active', false);
    }

    public function test_adding_cover_starts_a_subscription(): void
    {
        [$user, $agreement] = $this->customerWithAgreement();

        $this->actingAs($user)->postJson('/api/cover', ['types' => ['mot_cover']])
            ->assertOk()
            ->assertJsonPath('cover.0.active', true);

        $this->assertDatabaseHas('subscriptions', [
            'agreement_id' => $agreement->id,
            'type' => 'mot_cover',
            'ended_at' => null,
        ]);
    }

    public function test_removing_cover_ends_it_but_keeps_the_history(): void
    {
        [$user] = $this->customerWithAgreement();

        $this->actingAs($user)->postJson('/api/cover', ['types' => ['mot_cover']]);
        $this->actingAs($user)->postJson('/api/cover', ['types' => []]);

        // Row stays for the record, now ended.
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertNotNull(Subscription::first()->ended_at);
    }

    public function test_restarting_cover_is_a_new_row(): void
    {
        [$user] = $this->customerWithAgreement();

        $this->actingAs($user)->postJson('/api/cover', ['types' => ['mot_cover']]);
        $this->actingAs($user)->postJson('/api/cover', ['types' => []]);
        $this->actingAs($user)->postJson('/api/cover', ['types' => ['mot_cover']]);

        // Two rows: one ended, one active — a full start/stop/restart history.
        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertSame(1, Subscription::whereNull('ended_at')->count());
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        [$user] = $this->customerWithAgreement();

        $this->actingAs($user)->postJson('/api/cover', ['types' => ['rocket_insurance']])
            ->assertJsonValidationErrorFor('types.0');
    }

    public function test_cover_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/cover')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_account_can_hold_several_agreements(): void
    {
        $user = User::factory()->create();

        Agreement::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->agreements);
    }

    public function test_an_agreement_belongs_to_an_account(): void
    {
        $agreement = Agreement::factory()->create();

        $this->assertInstanceOf(User::class, $agreement->user);
    }

    public function test_agreement_numbers_are_unique(): void
    {
        Agreement::factory()->create(['agreement_number' => 'WW-1234-56789']);

        $this->expectException(QueryException::class);
        Agreement::factory()->create(['agreement_number' => 'WW-1234-56789']);
    }
}

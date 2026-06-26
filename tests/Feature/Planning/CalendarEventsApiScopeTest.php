<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class CalendarEventsApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_events(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownConnection = IntegrationConnection::factory()->for($user)->create();
        $otherConnection = IntegrationConnection::factory()->for($other)->create();

        $ownEvents = CalendarEvent::factory()->count(2)->create([
            'user_id' => $user->id,
            'integration_connection_id' => $ownConnection->id,
        ]);

        CalendarEvent::factory()->count(3)->create([
            'user_id' => $other->id,
            'integration_connection_id' => $otherConnection->id,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/calendar-events/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownEvents->pluck('id')->sort()->values()->all(), $returnedIds);
    }
}

<?php

namespace Tests\Feature\Lomkit;

use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Rest\Resource\BadgeAwardResource;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Rest\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UndeclaredRelationPathTest extends TestCase
{
    use RefreshDatabase;

    private const RELATION_REJECTED_MESSAGE = 'The relation is not valid or allowed for this resource.';

    /**
     * @return array<string, array{string}>
     */
    public static function searchableEndpoints(): array
    {
        return [
            'badge-awards' => ['badge-awards'],
            'badges' => ['badges'],
            'calendar-events' => ['calendar-events'],
            'challenges' => ['challenges'],
            'explored-cells' => ['explored-cells'],
            'goals' => ['goals'],
            'investment-transactions' => ['investment-transactions'],
            'moto-rides' => ['moto-rides'],
            'player-profiles' => ['player-profiles'],
            'positions' => ['positions'],
            'recurring-expenses' => ['recurring-expenses'],
            'sport-activities' => ['sport-activities'],
            'streaks' => ['streaks'],
            'tasks' => ['tasks'],
            'trip-routes' => ['trip-routes'],
            'users' => ['users'],
            'xp-entries' => ['xp-entries'],
        ];
    }

    #[Test]
    #[DataProvider('searchableEndpoints')]
    public function it_rejects_an_include_whose_first_segment_is_not_declared(string $endpoint): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson("/api/{$endpoint}/search", [
            'search' => ['includes' => [['relation' => 'user.badge']]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'search.includes.0.relation' => self::RELATION_REJECTED_MESSAGE,
        ]);
    }

    #[Test]
    public function it_rejects_an_aggregate_whose_first_segment_is_not_declared(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/search', [
            'search' => ['aggregates' => [['relation' => 'user.badge', 'type' => 'count']]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'search.aggregates.0.relation' => self::RELATION_REJECTED_MESSAGE,
        ]);
    }

    #[Test]
    public function it_rejects_a_nested_include_whose_first_segment_is_not_declared(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => ['includes' => [['relation' => 'badge', 'includes' => [['relation' => 'x.y']]]]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'search.includes.0.includes.0.relation' => self::RELATION_REJECTED_MESSAGE,
        ]);
    }

    #[Test]
    public function it_still_rejects_an_include_whose_nested_segment_is_not_declared(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => ['includes' => [['relation' => 'badge.foo']]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'search.includes.0.relation' => self::RELATION_REJECTED_MESSAGE,
        ]);
    }

    #[Test]
    public function it_still_includes_a_declared_relation(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => ['includes' => [['relation' => 'badge']]],
        ]);

        $response->assertOk();
        $this->assertSame($award->badge_id, $response->json('data.0.badge.id'));
    }

    #[Test]
    public function it_still_aggregates_a_declared_relation(): void
    {
        $user = User::factory()->create();
        BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => ['aggregates' => [['relation' => 'badge', 'type' => 'count']]],
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('data.0.badge_count'));
    }

    #[Test]
    public function it_exposes_no_exception_details_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/search', [
            'search' => ['includes' => [['relation' => 'user.badge']]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('exception');
    }

    #[Test]
    public function it_rejects_a_search_payload_that_is_not_an_array(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/search', [
            'search' => 'x',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search');
    }

    #[Test]
    public function it_rejects_a_numeric_include_relation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/search', [
            'search' => ['includes' => [['relation' => 123]]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'search.includes.0.relation' => self::RELATION_REJECTED_MESSAGE,
        ]);
    }

    #[Test]
    public function it_resolves_a_path_with_an_undeclared_first_segment_to_null(): void
    {
        $this->assertNull(app(BadgeAwardResource::class)->relation('user.badge'));
    }

    #[Test]
    public function it_resolves_a_path_with_an_undeclared_nested_segment_to_null(): void
    {
        $this->assertNull(app(BadgeAwardResource::class)->relation('badge.foo'));
    }

    #[Test]
    public function it_resolves_a_declared_relation_to_its_relation_instance(): void
    {
        $this->assertInstanceOf(BelongsTo::class, app(BadgeAwardResource::class)->relation('badge'));
    }
}

<?php

namespace Tests\Feature\Goals;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Models\Goal;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalsApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_goals(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownGoals = Goal::factory()->count(2)->manual()->create(['user_id' => $user->id]);
        Goal::factory()->count(3)->manual()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownGoals->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_authenticated_user_when_creating_a_goal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'title' => 'Run 100km',
                        'type' => GoalType::Sport->value,
                        'metric' => GoalMetric::SportDistance->value,
                        'target_value' => 100,
                        'status' => GoalStatus::Active->value,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('goals', [
            'title' => 'Run 100km',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_exposes_progress_computed_from_seeded_sport_data(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => 5000.0,
            'started_at' => now()->subDay(),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportDistance,
            'target_value' => 10,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/search', ['search' => []]);

        $response->assertOk();

        $payload = collect($response->json('data'))->firstWhere('id', $goal->id);

        $this->assertSame(5.0, (float) $payload['current_value']);
        $this->assertSame(50.0, (float) $payload['progress_percentage']);
    }

    #[Test]
    public function it_exposes_progress_computed_from_seeded_finance_data(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);
        InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
            'type' => TransactionType::Buy,
            'quantity' => 10,
            'unit_price' => 50,
            'executed_at' => now()->subDays(2),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Finance,
            'metric' => GoalMetric::FinanceInvestedCapital,
            'target_value' => 1000,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/search', ['search' => []]);

        $payload = collect($response->json('data'))->firstWhere('id', $goal->id);

        $this->assertSame(500.0, (float) $payload['current_value']);
        $this->assertSame(50.0, (float) $payload['progress_percentage']);
    }

    #[Test]
    public function it_forbids_updating_another_users_goal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $goal = Goal::factory()->manual()->create(['user_id' => $other->id, 'title' => 'Original']);

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $goal->id, 'attributes' => ['title' => 'Hijacked']],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'title' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_and_delete_their_goal(): void
    {
        $user = User::factory()->create();

        $goal = Goal::factory()->manual()->create(['user_id' => $user->id, 'title' => 'Original']);

        $this->actingAs($user, 'api')->postJson('/api/goals/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $goal->id, 'attributes' => ['title' => 'Updated']],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'title' => 'Updated']);

        $this->actingAs($user, 'api')->deleteJson('/api/goals', [
            'resources' => [$goal->id],
        ])->assertOk();

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }
}

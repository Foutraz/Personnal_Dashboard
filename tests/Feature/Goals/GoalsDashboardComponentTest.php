<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Livewire\GoalsDashboard;
use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalsDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_goal_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->set('newTitle', 'Courir 100 km')
            ->set('newMetric', GoalMetric::SportDistance->value)
            ->set('newTargetValue', '100')
            ->call('createGoal');

        $this->assertDatabaseHas('goals', [
            'title' => 'Courir 100 km',
            'user_id' => $user->id,
            'type' => GoalType::Sport->value,
            'metric' => GoalMetric::SportDistance->value,
        ]);
    }

    #[Test]
    public function it_updates_a_manual_goal_and_completes_it(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->manual()->create([
            'user_id' => $user->id,
            'target_value' => 40,
            'manual_current_value' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->call('startEditing', $goal->id)
            ->set('editManualValue', '40')
            ->call('updateManualValue');

        $goal->refresh();

        $this->assertSame(40.0, (float) $goal->manual_current_value);
        $this->assertSame(GoalStatus::Achieved, $goal->status);
    }

    #[Test]
    public function it_filters_goals_by_type(): void
    {
        $user = User::factory()->create();
        $sportGoal = Goal::factory()->sportDistance()->create(['user_id' => $user->id]);
        $personalGoal = Goal::factory()->manual()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->set('typeFilter', GoalType::Sport->value)
            ->assertSee($sportGoal->title)
            ->assertDontSee($personalGoal->title);
    }

    #[Test]
    public function it_archives_a_goal_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->manual()->create(['user_id' => $user->id, 'status' => GoalStatus::Active]);

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->call('archiveGoal', $goal->id);

        $this->assertSame(GoalStatus::Archived, $goal->refresh()->status);
    }

    #[Test]
    public function it_deletes_a_goal_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->manual()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->call('deleteGoal', $goal->id);

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    #[Test]
    public function it_does_not_delete_another_users_goal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $goal = Goal::factory()->manual()->create(['user_id' => $other->id]);

        Livewire::actingAs($user)
            ->test(GoalsDashboard::class)
            ->call('deleteGoal', $goal->id);

        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'deleted_at' => null]);
    }
}

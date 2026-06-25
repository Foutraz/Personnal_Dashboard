<?php

namespace Functional\Goals\Database\Factories;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Goal>
     */
    protected $model = Goal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metric = faker()->randomElement(GoalMetric::cases());

        return [
            'user_id' => User::factory(),
            'title' => faker()->words(3),
            'description' => faker()->words(8),
            'type' => $metric->type(),
            'metric' => $metric,
            'target_value' => faker()->float(100, 5000, 2),
            'manual_current_value' => $metric === GoalMetric::Manual ? faker()->float(0, 100, 2) : null,
            'unit' => $metric->defaultUnit(),
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonths(2),
            'status' => GoalStatus::Active,
        ];
    }

    /**
     * Indicate that the goal tracks a sport distance metric.
     */
    public function sportDistance(): static
    {
        return $this->state(fn (): array => [
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportDistance,
            'unit' => GoalMetric::SportDistance->defaultUnit(),
            'manual_current_value' => null,
        ]);
    }

    /**
     * Indicate that the goal tracks a manual personal metric.
     */
    public function manual(): static
    {
        return $this->state(fn (): array => [
            'type' => GoalType::Personal,
            'metric' => GoalMetric::Manual,
            'manual_current_value' => 0,
        ]);
    }
}

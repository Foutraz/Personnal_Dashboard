<?php

namespace Functional\Todo\Database\Factories;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Task>
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => faker()->words(4),
            'description' => faker()->boolean() ? faker()->words(12) : null,
            'priority' => faker()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Pending,
            'due_at' => faker()->boolean() ? faker()->dateTime('now', '+1 month') : null,
            'completed_at' => null,
            'position' => faker()->number(0, 20),
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Done,
            'completed_at' => now(),
        ]);
    }
}

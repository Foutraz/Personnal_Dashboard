<?php

namespace Functional\Todo\Database\Factories;

use Functional\Todo\Models\Task;
use Functional\Todo\Models\TaskReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReminder>
 */
class TaskReminderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TaskReminder>
     */
    protected $model = TaskReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'remind_at' => faker()->dateTime('now', '+1 week'),
            'sent' => false,
        ];
    }
}

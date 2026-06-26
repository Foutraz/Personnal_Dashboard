<?php

namespace Functional\Planning\Database\Factories;

use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CalendarEvent>
     */
    protected $model = CalendarEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $provider = faker()->randomElement([IntegrationProvider::GoogleCalendar, IntegrationProvider::OutlookCalendar]);
        $startsAt = Carbon::instance(faker()->dateTime('now', '+60 days'));

        return [
            'integration_connection_id' => IntegrationConnection::factory()->for($user)->state(['provider' => $provider]),
            'user_id' => $user,
            'provider' => $provider,
            'external_id' => (string) faker()->unique()->number(100000000, 999999999),
            'title' => faker()->words(3),
            'description' => faker()->boolean() ? faker()->words(10) : null,
            'location' => faker()->boolean() ? faker()->words(2) : null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHour(),
            'all_day' => false,
            'external_link' => faker()->url(),
            'raw' => [],
        ];
    }

    /**
     * Indicate that the event spans the whole day.
     */
    public function allDay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'all_day' => true,
            'ends_at' => null,
        ]);
    }
}

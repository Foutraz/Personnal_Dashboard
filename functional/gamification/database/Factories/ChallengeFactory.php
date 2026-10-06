<?php

namespace Functional\Gamification\Database\Factories;

use Carbon\CarbonImmutable;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    private const FAILED_PROGRESS_RATIO = 0.4;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Challenge>
     */
    protected $model = Challenge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $week = app(GamificationCalendar::class)->currentWeek();
        $template = ChallengeTemplateKey::SportDistance;
        $baseline = faker()->number(10, 40);

        return [
            'user_id' => User::factory(),
            'week_key' => $week->key(),
            'template_key' => $template,
            'domain' => $template->domain(),
            'metric' => $template->metric(),
            'starts_at' => $week->startsAt,
            'ends_at' => $week->endsAt,
            'closes_at' => fn (array $attributes): CarbonImmutable => CarbonImmutable::parse($attributes['ends_at'])->addHours(ChallengeSettings::fromConfig()->closingGraceHours),
            'baseline_value' => $baseline,
            'target_value' => $baseline + faker()->number(1, 5),
            'current_value' => 0,
            'xp_reward' => ChallengeSettings::fromConfig()->xpReward,
            'status' => ChallengeStatus::Proposed,
            'accepted_at' => null,
            'resolved_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Completed,
            'current_value' => fn (array $attributes): mixed => $attributes['target_value'],
            'accepted_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Failed,
            'current_value' => fn (array $attributes): float => round($attributes['target_value'] * self::FAILED_PROGRESS_RATIO, 2),
            'accepted_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Declined,
            'resolved_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeStatus::Expired,
            'resolved_at' => now(),
        ]);
    }

    public function forWeek(GamificationWeek $week): static
    {
        return $this->state(fn (): array => [
            'week_key' => $week->key(),
            'starts_at' => $week->startsAt,
            'ends_at' => $week->endsAt,
        ]);
    }

    public function forTemplate(ChallengeTemplateKey $template): static
    {
        return $this->state(fn (): array => [
            'template_key' => $template,
            'domain' => $template->domain(),
            'metric' => $template->metric(),
        ]);
    }
}

<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\RunChallengeCycle;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\ChallengeCycleOutcome;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunChallengeCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    #[Test]
    public function it_proposes_the_current_week_resolves_the_open_challenges_and_records_the_xp(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $previousChallenge = Challenge::factory()->accepted()->forWeek($this->currentWeek()->previous())->create([
            'user_id' => $user->id,
            'target_value' => 28,
        ]);

        $outcome = $this->cycle()->handle($user);

        $this->assertInstanceOf(ChallengeCycleOutcome::class, $outcome);
        $this->assertSame('2026-W40', $outcome->week->key());
        $this->assertSame([ChallengeTemplateKey::SportDistance], $outcome->proposed->map(fn (Challenge $challenge): ChallengeTemplateKey => $challenge->template_key)->all());
        $this->assertSame('2026-W40', $outcome->proposed->sole()->week_key);
        $this->assertTrue($outcome->completed->sole()->is($previousChallenge));
        $this->assertSame(ChallengeStatus::Completed, $previousChallenge->fresh()->status);
        $entry = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->sole();
        $this->assertSame(XpSourceType::Challenge->value, $entry->source_type);
        $this->assertSame($previousChallenge->id, $entry->source_id);
        $this->assertSame(50, $entry->points);
        $this->assertSame('2026-10-01 10:00:00', $entry->occurred_at->toDateTimeString());
    }

    #[Test]
    public function it_reports_nothing_on_the_second_pass(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        Challenge::factory()->accepted()->forWeek($this->currentWeek()->previous())->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->cycle()->handle($user);

        $outcome = $this->cycle()->handle($user);

        $this->assertCount(0, $outcome->proposed);
        $this->assertCount(0, $outcome->completed);
        $this->assertSame(1, XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->count());
        $this->assertSame(2, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_never_generates_a_past_week(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->cycle()->handle($user);

        $this->assertSame(['2026-W42'], Challenge::query()->whereBelongsTo($user)->pluck('week_key')->unique()->values()->all());
    }

    #[Test]
    public function it_proposes_nothing_for_a_user_without_history_and_reports_the_current_week(): void
    {
        $user = User::factory()->create();

        $outcome = $this->cycle()->handle($user);

        $this->assertSame('2026-W40', $outcome->week->key());
        $this->assertCount(0, $outcome->proposed);
        $this->assertCount(0, $outcome->completed);
        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_reads_the_reward_settings_on_every_call(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $cycle = $this->cycle();
        config(['gamification.challenges.xp_reward' => 80]);

        $cycle->handle($user);

        $this->assertSame(80, Challenge::query()->whereBelongsTo($user)->sole()->xp_reward);
    }

    #[Test]
    public function it_keeps_the_closing_grace_frozen_on_each_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallengeOfWeekForty($user);
        $cycle = $this->cycle();
        config(['gamification.challenges.closing_grace_hours' => 0]);
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $cycle->handle($user);

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);

        config(['gamification.challenges.closing_grace_hours' => 168]);
        $this->travelTo(Carbon::parse('2026-10-06 22:00:00', 'UTC'));

        $cycle->handle($user);

        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_fails_fast_on_an_invalid_challenge_config_without_persisting_anything(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        config(['gamification.challenges.xp_reward' => 0]);

        $this->assertThrows(fn () => $this->cycle()->handle($user), InvalidChallengeConfigException::class);

        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_completes_with_xp_a_challenge_reached_only_by_an_activity_synced_after_the_grace(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallengeOfWeekForty($user);
        $this->travelTo(Carbon::parse('2026-10-06 02:00:00', 'UTC'));
        $this->cycle()->handle($user);
        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
        $this->travelTo(Carbon::parse('2026-10-06 22:30:00', 'UTC'));
        $this->inWeekActivity($user);

        $outcome = $this->cycle()->handle($user);

        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
        $this->assertTrue($outcome->completed->sole()->is($challenge));
        $entry = XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->sole();
        $this->assertSame($challenge->id, $entry->source_id);
        $this->assertSame(50, $entry->points);
    }

    #[Test]
    public function it_keeps_a_failed_challenge_failed_without_xp_when_the_activity_arrives_after_the_verdict(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallengeOfWeekForty($user);
        $this->travelTo(Carbon::parse('2026-10-06 22:30:00', 'UTC'));
        $this->cycle()->handle($user);
        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
        $this->inWeekActivity($user);
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));

        $outcome = $this->cycle()->handle($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Failed, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
        $this->assertCount(0, $outcome->completed);
        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::ChallengeCompleted->value)->count());
    }

    private function cycle(): RunChallengeCycle
    {
        return $this->app->make(RunChallengeCycle::class);
    }

    private function currentWeek(): GamificationWeek
    {
        return $this->app->make(GamificationCalendar::class)->currentWeek();
    }

    private function acceptedChallengeOfWeekForty(User $user): Challenge
    {
        $week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-10-01 10:00:00', 'UTC'));

        return Challenge::factory()->accepted()->forWeek($week)->create(['user_id' => $user->id, 'target_value' => 28]);
    }

    private function inWeekActivity(User $user): void
    {
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => 30000.0,
            'moving_time' => 0,
            'total_elevation_gain' => 0,
            'started_at' => Carbon::parse('2026-10-02 08:00:00', 'UTC'),
        ]);
    }

    private function sportHistory(User $user): void
    {
        foreach (['2026-09-02' => 10000.0, '2026-09-09' => 20000.0, '2026-09-16' => 30000.0, '2026-09-23' => 40000.0] as $day => $distance) {
            SportActivity::factory()->create([
                'user_id' => $user->id,
                'distance' => $distance,
                'moving_time' => 0,
                'total_elevation_gain' => 0,
                'started_at' => Carbon::parse("{$day} 12:00:00", 'UTC'),
            ]);
        }
    }
}

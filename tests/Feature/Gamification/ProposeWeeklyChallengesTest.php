<?php

namespace Tests\Feature\Gamification;

use Functional\Exploration\Models\ExploredCell;
use Functional\Gamification\Actions\ProposeWeeklyChallenges;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Goals\Enums\GoalMetric;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProposeWeeklyChallengesTest extends TestCase
{
    use RefreshDatabase;

    private ProposeWeeklyChallenges $action;

    private GamificationWeek $week;

    private int $cellSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-28 06:00:00', 'UTC'));
        $this->action = $this->app->make(ProposeWeeklyChallenges::class);
        $this->week = $this->app->make(GamificationCalendar::class)->currentWeek();
    }

    #[Test]
    public function it_proposes_the_sport_distance_from_the_median_of_the_four_previous_weeks(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);

        $proposals = $this->action->handle($user, $this->week);

        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertTrue(Str::isUlid($challenge->id));
        $this->assertSame(strtolower($challenge->id), $challenge->id);
        $this->assertSame(ChallengeTemplateKey::SportDistance, $challenge->template_key);
        $this->assertSame(GamificationDomain::Sport, $challenge->domain);
        $this->assertSame(GoalMetric::SportDistance, $challenge->metric);
        $this->assertSame('2026-W40', $challenge->week_key);
        $this->assertSame('2026-09-27 22:00:00', $challenge->starts_at->toDateTimeString());
        $this->assertSame('2026-10-04 22:00:00', $challenge->ends_at->toDateTimeString());
        $this->assertSame('25.00', $challenge->baseline_value);
        $this->assertSame('28.00', $challenge->target_value);
        $this->assertSame('0.00', $challenge->current_value);
        $this->assertSame(50, $challenge->xp_reward);
        $this->assertSame(ChallengeStatus::Proposed, $challenge->status);
        $this->assertNull($challenge->accepted_at);
        $this->assertNull($challenge->resolved_at);
        $this->assertCount(1, $proposals);
        $this->assertTrue($proposals->first()->is($challenge));
    }

    #[Test]
    public function it_stamps_the_creation_moments(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->action->handle($user, $this->week);

        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertSame('2026-09-28 06:00:00', $challenge->created_at->toDateTimeString());
        $this->assertSame('2026-09-28 06:00:00', $challenge->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_freezes_the_closing_instant_with_the_default_grace(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->action->handle($user, $this->week);

        $this->assertSame('2026-10-06 22:00:00', Challenge::query()->whereBelongsTo($user)->sole()->closes_at->toDateTimeString());
    }

    #[Test]
    public function it_freezes_the_closing_instant_with_the_configured_grace(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 72]);
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->action->handle($user, $this->week);

        $this->assertSame('2026-10-07 22:00:00', Challenge::query()->whereBelongsTo($user)->sole()->closes_at->toDateTimeString());
    }

    #[Test]
    public function it_gives_every_challenge_of_the_set_the_same_closing_instant(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->motoHistory($user);

        $this->action->handle($user, $this->week);

        $this->assertGreaterThan(1, Challenge::query()->whereBelongsTo($user)->count());
        $this->assertSame(
            ['2026-10-06 22:00:00'],
            Challenge::query()->whereBelongsTo($user)->get()->map(fn (Challenge $challenge): string => $challenge->closes_at->toDateTimeString())->unique()->values()->all(),
        );
    }

    #[Test]
    public function it_follows_the_configured_reward_and_stretch(): void
    {
        config(['gamification.challenges.xp_reward' => 80, 'gamification.challenges.stretch_ratio' => 0]);
        $user = User::factory()->create();
        $this->sportHistory($user);

        $this->app->make(ProposeWeeklyChallenges::class)->handle($user, $this->week);

        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertSame(80, $challenge->xp_reward);
        $this->assertSame('25.00', $challenge->target_value);
    }

    #[Test]
    public function it_rotates_the_sport_template_with_the_iso_week(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 06:00:00', 'UTC'));
        $user = User::factory()->create();
        $this->sportHistory($user, withFirstWeek: false);

        $this->action->handle($user, $this->app->make(GamificationCalendar::class)->currentWeek());

        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertSame('2026-W41', $challenge->week_key);
        $this->assertSame(ChallengeTemplateKey::SportActivityCount, $challenge->template_key);
        $this->assertSame(GoalMetric::SportActivityCount, $challenge->metric);
        $this->assertSame('1.00', $challenge->baseline_value);
        $this->assertSame('2.00', $challenge->target_value);
    }

    #[Test]
    public function it_proposes_one_challenge_per_active_domain_in_the_domain_order(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->motoHistory($user);
        $this->explorationHistory($user);

        $proposals = $this->action->handle($user, $this->week);

        $this->assertSame(
            [ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::MotoDistance, ChallengeTemplateKey::ExplorationCells],
            $proposals->map(fn (Challenge $challenge): ChallengeTemplateKey => $challenge->template_key)->all(),
        );
        $this->assertCount(3, Challenge::query()->whereBelongsTo($user)->get());
        $this->assertTargets($user, ChallengeTemplateKey::SportDistance, '25.00', '28.00');
        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '130.00', '150.00');
        $this->assertTargets($user, ChallengeTemplateKey::ExplorationCells, '5.00', '10.00');
    }

    #[Test]
    public function it_creates_the_set_only_once_per_week(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->motoHistory($user);
        $this->explorationHistory($user);
        $created = $this->action->handle($user, $this->week);

        $second = $this->action->handle($user, $this->week);

        $this->assertTrue($second->isEmpty());
        $this->assertEqualsCanonicalizing($created->pluck('id')->all(), Challenge::query()->whereBelongsTo($user)->pluck('id')->all());
        $this->assertCount(3, Challenge::query()->whereBelongsTo($user)->get());
    }

    #[Test]
    public function it_freezes_an_existing_set_when_more_history_arrives(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->action->handle($user, $this->week);
        $this->motoHistory($user);

        $proposals = $this->action->handle($user, $this->week);

        $this->assertTrue($proposals->isEmpty());
        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->where('domain', GamificationDomain::Moto)->count());
    }

    #[Test]
    public function it_proposes_the_next_week_even_when_the_previous_set_exists(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->action->handle($user, $this->week);
        $this->travelTo(Carbon::parse('2026-10-05 06:00:00', 'UTC'));

        $proposals = $this->action->handle($user, $this->app->make(GamificationCalendar::class)->currentWeek());

        $this->assertCount(1, $proposals);
        $this->assertSame(['2026-W40', '2026-W41'], Challenge::query()->whereBelongsTo($user)->orderBy('week_key')->pluck('week_key')->all());
    }

    #[Test]
    public function it_proposes_nothing_without_any_history(): void
    {
        $user = User::factory()->create();

        $proposals = $this->action->handle($user, $this->week);

        $this->assertTrue($proposals->isEmpty());
        $this->assertSame(0, Challenge::query()->count());
    }

    #[Test]
    public function it_proposes_nothing_with_a_single_active_week_until_the_history_is_backfilled(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-23 12:00:00', 40000.0);
        $this->action->handle($user, $this->week);
        $this->assertSame(0, Challenge::query()->count());

        $this->activityAt($user, '2026-09-09 12:00:00', 20000.0);
        $this->activityAt($user, '2026-09-16 12:00:00', 30000.0);
        $this->activityAt($user, '2026-09-02 12:00:00', 10000.0);
        $proposals = $this->action->handle($user, $this->week);

        $this->assertCount(1, $proposals);
        $this->assertSame(1, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_ignores_the_history_of_other_users(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-23 12:00:00', 40000.0);
        $this->sportHistory(User::factory()->create());

        $proposals = $this->action->handle($user, $this->week);

        $this->assertTrue($proposals->isEmpty());
        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_counts_the_sunday_night_in_paris_as_part_of_the_history(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-16 12:00:00', 10000.0);
        $this->activityAt($user, '2026-09-27 21:30:00', 10000.0);

        $proposals = $this->action->handle($user, $this->week);

        $this->assertCount(1, $proposals);
        $this->assertSame('5.00', $proposals->first()->baseline_value);
        $this->assertSame('6.00', $proposals->first()->target_value);
    }

    #[Test]
    public function it_leaves_the_monday_night_in_paris_out_of_the_history(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-16 12:00:00', 10000.0);
        $this->activityAt($user, '2026-09-27 22:30:00', 10000.0);

        $proposals = $this->action->handle($user, $this->week);

        $this->assertTrue($proposals->isEmpty());
    }

    #[Test]
    public function it_keeps_the_proposal_of_a_concurrent_run_and_does_not_return_it(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $concurrentlyInserted = false;
        DB::listen(function (QueryExecuted $query) use (&$concurrentlyInserted, $user): void {
            if ($concurrentlyInserted) {
                return;
            }

            $concurrentlyInserted = true;
            Challenge::factory()->forWeek($this->week)->create(['user_id' => $user->id, 'baseline_value' => 99, 'target_value' => 111]);
        });

        $proposals = $this->action->handle($user, $this->week);

        $this->assertTrue($proposals->isEmpty());
        $challenge = Challenge::query()->whereBelongsTo($user)->sole();
        $this->assertSame('111.00', $challenge->target_value);
    }

    #[Test]
    public function it_runs_one_existence_query_and_one_measure_per_template_for_a_user_without_data(): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();

        $this->action->handle($user, $this->week);

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }

    #[Test]
    public function it_runs_exactly_eight_queries_for_a_user_without_data(): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();

        $this->action->handle($user, $this->week);

        $this->assertCount(1 + count(ChallengeTemplateKey::cases()), DB::getQueryLog());
        $this->assertCount(8, DB::getQueryLog());
    }

    #[Test]
    public function it_runs_one_grouped_history_query_per_template_whatever_the_history(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->motoHistory($user);
        $this->explorationHistory($user);
        DB::enableQueryLog();

        $this->action->handle($user, $this->week);

        $existence = 1;
        $historyPerTemplate = count(ChallengeTemplateKey::cases());
        $insertAndReread = 2;
        $this->assertCount($existence + $historyPerTemplate + $insertAndReread, DB::getQueryLog());
        $this->assertCount(10, DB::getQueryLog());
    }

    #[Test]
    public function it_builds_the_moto_history_from_the_rides_recorded_before_the_closing_of_their_week(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, thirdWeekRecordedAtUtc: '2026-09-22 22:00:00');

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '110.00', '130.00');
    }

    #[Test]
    public function it_counts_the_moto_ride_recorded_one_second_before_the_closing_of_its_week(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, thirdWeekRecordedAtUtc: '2026-09-22 21:59:59');

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '130.00', '150.00');
    }

    #[Test]
    public function it_counts_the_moto_ride_recorded_after_the_end_of_its_week_inside_the_grace(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, fourthWeekRecordedAtUtc: '2026-09-28 05:00:00');

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '130.00', '150.00');
    }

    #[Test]
    public function it_ignores_a_ride_whose_distance_was_corrected_after_the_closing_of_its_week(): void
    {
        $user = User::factory()->create();
        $rides = $this->motoHistory($user);
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00', 'UTC'));
        $rides[2]->update(['distance' => 145.0]);

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '110.00', '130.00');
    }

    #[Test]
    public function it_proposes_no_moto_challenge_to_a_user_who_records_the_history_when_the_challenges_are_proposed(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        $this->rideAt($user, '2026-09-16 12:00:00', 140.0, '2026-09-28 06:00:00');
        $this->rideAt($user, '2026-09-23 12:00:00', 160.0, '2026-09-28 06:00:00');

        $this->action->handle($user, $this->week);

        $this->assertSame(0, Challenge::query()->whereBelongsTo($user)->where('domain', GamificationDomain::Moto)->count());
        $this->assertSame(1, Challenge::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_builds_the_sport_history_from_the_synchronised_activities_whenever_they_were_created(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user, createdAtUtc: '2026-09-28 05:00:00');

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::SportDistance, '25.00', '28.00');
    }

    #[Test]
    public function it_applies_the_configured_grace_to_the_closing_of_the_history_weeks(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 72]);
        $user = User::factory()->create();
        $this->motoHistory($user, thirdWeekRecordedAtUtc: '2026-09-22 22:00:00');

        $this->action->handle($user, $this->week);

        $this->assertTargets($user, ChallengeTemplateKey::MotoDistance, '130.00', '150.00');
    }

    private function assertTargets(User $user, ChallengeTemplateKey $template, string $baseline, string $target): void
    {
        $challenge = Challenge::query()->whereBelongsTo($user)->where('template_key', $template)->sole();

        $this->assertSame($baseline, $challenge->baseline_value);
        $this->assertSame($target, $challenge->target_value);
    }

    private function sportHistory(User $user, bool $withFirstWeek = true, ?string $createdAtUtc = null): void
    {
        if ($withFirstWeek) {
            $this->activityAt($user, '2026-09-02 12:00:00', 10000.0, $createdAtUtc);
        }

        $this->activityAt($user, '2026-09-09 12:00:00', 20000.0, $createdAtUtc);
        $this->activityAt($user, '2026-09-16 12:00:00', 30000.0, $createdAtUtc);
        $this->activityAt($user, '2026-09-23 12:00:00', 40000.0, $createdAtUtc);
    }

    /**
     * @return list<MotoRide>
     */
    private function motoHistory(User $user, ?string $thirdWeekRecordedAtUtc = null, ?string $fourthWeekRecordedAtUtc = null): array
    {
        return [
            $this->rideAt($user, '2026-09-02 12:00:00', 100.0),
            $this->rideAt($user, '2026-09-09 12:00:00', 120.0),
            $this->rideAt($user, '2026-09-16 12:00:00', 140.0, $thirdWeekRecordedAtUtc),
            $this->rideAt($user, '2026-09-23 12:00:00', 160.0, $fourthWeekRecordedAtUtc),
        ];
    }

    private function rideAt(User $user, string $startedAtUtc, float $kilometers, ?string $recordedAtUtc = null): MotoRide
    {
        return MotoRide::factory()->create([
            'user_id' => $user->id,
            'distance' => $kilometers,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
            'recorded_at' => Carbon::parse($recordedAtUtc ?? $startedAtUtc, 'UTC'),
        ]);
    }

    private function explorationHistory(User $user): void
    {
        foreach (['2026-09-09' => 4, '2026-09-16' => 6, '2026-09-23' => 8] as $day => $cells) {
            for ($index = 0; $index < $cells; $index++) {
                ExploredCell::factory()->create([
                    'user_id' => $user->id,
                    'cell_key' => 'cell:'.$this->cellSequence++,
                    'first_seen_at' => Carbon::parse("{$day} 12:00:00", 'UTC'),
                ]);
            }
        }
    }

    private function activityAt(User $user, string $startedAtUtc, float $distance, ?string $createdAtUtc = null): SportActivity
    {
        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => $distance,
            'moving_time' => 0,
            'total_elevation_gain' => 0,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
            ...$this->creationAttributes($createdAtUtc),
        ]);
    }

    /**
     * @return array<string, Carbon>
     */
    private function creationAttributes(?string $createdAtUtc): array
    {
        return $createdAtUtc === null ? [] : ['created_at' => Carbon::parse($createdAtUtc, 'UTC')];
    }
}

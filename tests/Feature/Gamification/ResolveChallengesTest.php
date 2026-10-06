<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\ResolveChallenges;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResolveChallengesTest extends TestCase
{
    use RefreshDatabase;

    private GamificationWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-03 12:00:00', 'UTC'));
        $this->week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-09-30 12:00:00', 'UTC'));
    }

    #[Test]
    public function it_completes_an_accepted_challenge_as_soon_as_the_week_reaches_the_target(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 12000.0);
        $this->activityAt($user, '2026-10-01 08:00:00', 18000.0);

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('30.00', $persisted->current_value);
        $this->assertSame('2026-10-03 12:00:00', $persisted->resolved_at->toDateTimeString());
        $this->assertSame('2026-09-29 08:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertCount(1, $completed);
        $this->assertTrue($completed->first()->is($challenge));
    }

    #[Test]
    public function it_keeps_an_accepted_challenge_open_while_the_week_stays_below_the_target(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 20000.0);

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('20.00', $persisted->current_value);
        $this->assertNull($persisted->resolved_at);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_completes_a_challenge_whose_measure_rounds_up_to_the_target(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 27996.0);

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('28.00', $persisted->current_value);
    }

    #[Test]
    public function it_keeps_an_unreached_challenge_accepted_until_the_closing_grace_ends(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 20000.0);
        $this->travelTo(Carbon::parse('2026-10-06 21:59:59', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('20.00', $persisted->current_value);
    }

    #[Test]
    public function it_fails_an_unreached_challenge_once_the_closing_grace_has_ended(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 20000.0);
        $this->travelTo(Carbon::parse('2026-10-06 22:00:00', 'UTC'));

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Failed, $persisted->status);
        $this->assertSame('20.00', $persisted->current_value);
        $this->assertSame('2026-10-06 22:00:00', $persisted->resolved_at->toDateTimeString());
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_counts_a_late_synchronisation_started_inside_the_week(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-10-04 15:00:00', 30000.0);
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_ignores_an_activity_started_after_the_end_of_the_week(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-10-04 22:30:00', 30000.0);
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
    }

    #[Test]
    public function it_keeps_a_proposed_challenge_until_the_week_ends_and_records_its_progress(): void
    {
        $user = User::factory()->create();
        $challenge = $this->proposedChallenge($user);
        $this->activityAt($user, '2026-10-01 08:00:00', 12000.0);
        $this->travelTo(Carbon::parse('2026-10-04 21:59:59', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Proposed, $persisted->status);
        $this->assertSame('12.00', $persisted->current_value);
        $this->assertNull($persisted->resolved_at);
    }

    #[Test]
    public function it_expires_a_proposed_challenge_when_the_week_ends(): void
    {
        $user = User::factory()->create();
        $challenge = $this->proposedChallenge($user);
        $this->activityAt($user, '2026-10-01 08:00:00', 12000.0);
        $this->travelTo(Carbon::parse('2026-10-04 22:00:00', 'UTC'));

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Expired, $persisted->status);
        $this->assertSame('2026-10-04 22:00:00', $persisted->resolved_at->toDateTimeString());
        $this->assertSame('12.00', $persisted->current_value);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_leaves_a_proposed_challenge_with_a_reached_target_waiting_for_the_acceptance(): void
    {
        $user = User::factory()->create();
        $challenge = $this->proposedChallenge($user);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Proposed, $persisted->status);
        $this->assertSame('30.00', $persisted->current_value);
        $this->assertNull($persisted->resolved_at);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_keeps_a_completed_challenge_and_its_value_when_the_activities_disappear(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $activity = $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);
        $this->resolve($user);
        $activity->delete();
        $this->travelTo(Carbon::parse('2026-10-04 09:00:00', 'UTC'));

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('30.00', $persisted->current_value);
        $this->assertSame('2026-10-03 12:00:00', $persisted->resolved_at->toDateTimeString());
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_never_modifies_a_declined_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->declined()->forWeek($this->week)->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);
        $this->travelTo(Carbon::parse('2026-10-09 09:00:00', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Declined, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
        $this->assertSame('2026-10-03 12:00:00', $persisted->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_never_modifies_a_failed_or_an_expired_challenge(): void
    {
        $user = User::factory()->create();
        $failed = Challenge::factory()->failed()->forWeek($this->week)->create(['user_id' => $user->id, 'target_value' => 28, 'current_value' => 11.2]);
        $expired = Challenge::factory()->expired()->forWeek($this->week)->forTemplate(ChallengeTemplateKey::MotoDistance)->create(['user_id' => $user->id, 'target_value' => 150]);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);
        $this->travelTo(Carbon::parse('2026-10-09 09:00:00', 'UTC'));

        $completed = $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $failed->fresh()->status);
        $this->assertSame('11.20', $failed->fresh()->current_value);
        $this->assertSame(ChallengeStatus::Expired, $expired->fresh()->status);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_updates_only_the_progress_of_an_open_challenge_that_keeps_its_status(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 20000.0);
        $this->travelTo(Carbon::parse('2026-10-03 18:45:00', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('2026-09-29 08:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertNull($persisted->resolved_at);
        $this->assertSame('2026-10-03 18:45:00', $persisted->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_keeps_the_status_out_of_the_progress_update(): void
    {
        $user = User::factory()->create();
        $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-09-29 08:00:00', 20000.0);
        $updates = [];
        DB::listen(function (QueryExecuted $query) use (&$updates): void {
            if (str_starts_with($query->sql, 'update')) {
                $updates[] = $query->sql;
            }
        });

        $this->resolve($user);

        $this->assertCount(1, $updates);
        $this->assertStringNotContainsString('status', explode(' where ', $updates[0])[0]);
    }

    #[Test]
    public function it_does_not_rewrite_a_challenge_declined_while_its_progress_was_measured(): void
    {
        $user = User::factory()->create();
        $challenge = $this->proposedChallenge($user);
        $this->activityAt($user, '2026-10-01 08:00:00', 12000.0);
        $this->travelTo(Carbon::parse('2026-10-03 18:45:00', 'UTC'));
        $declined = false;
        DB::listen(function (QueryExecuted $query) use (&$declined, $challenge): void {
            if (! $declined && str_contains($query->sql, 'sport_activities')) {
                $declined = true;
                DB::table('challenges')->where('id', $challenge->id)->update(['status' => ChallengeStatus::Declined->value]);
            }
        });

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertTrue($declined);
        $this->assertSame(ChallengeStatus::Declined, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
        $this->assertSame('2026-10-03 12:00:00', $persisted->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_measures_each_challenge_on_its_own_week(): void
    {
        $user = User::factory()->create();
        $previousWeek = $this->week->previous();
        $currentChallenge = $this->acceptedChallenge($user);
        $previousChallenge = Challenge::factory()->accepted()->forWeek($previousWeek)->create(['user_id' => $user->id, 'target_value' => 28, 'accepted_at' => Carbon::parse('2026-09-22 08:00:00', 'UTC')]);
        $this->activityAt($user, '2026-09-23 08:00:00', 9000.0);
        $this->activityAt($user, '2026-10-01 08:00:00', 5000.0);

        $this->resolve($user);

        $this->assertSame('5.00', $currentChallenge->fresh()->current_value);
        $this->assertSame('9.00', $previousChallenge->fresh()->current_value);
    }

    #[Test]
    public function it_measures_the_metric_of_the_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->forWeek($this->week)->forTemplate(ChallengeTemplateKey::MotoDistance)->create(['user_id' => $user->id, 'target_value' => 150]);
        MotoRide::factory()->create(['user_id' => $user->id, 'distance' => 160.0, 'started_at' => Carbon::parse('2026-10-01 08:00:00', 'UTC')]);
        $this->activityAt($user, '2026-10-01 08:00:00', 5000.0);

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('160.00', $persisted->current_value);
    }

    #[Test]
    public function it_resolves_only_the_challenges_of_the_given_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherChallenge = $this->acceptedChallenge($otherUser);
        $this->activityAt($otherUser, '2026-10-01 08:00:00', 30000.0);
        $this->acceptedChallenge($user);

        $completed = $this->resolve($user);

        $this->assertSame(ChallengeStatus::Accepted, $otherChallenge->fresh()->status);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_returns_the_completed_challenges_ordered_by_week_then_template(): void
    {
        $user = User::factory()->create();
        $currentSport = $this->acceptedChallenge($user);
        $currentMoto = Challenge::factory()->accepted()->forWeek($this->week)->forTemplate(ChallengeTemplateKey::MotoDistance)->create(['user_id' => $user->id, 'target_value' => 150]);
        $previousSport = Challenge::factory()->accepted()->forWeek($this->week->previous())->create(['user_id' => $user->id, 'target_value' => 28]);
        $this->activityAt($user, '2026-09-23 08:00:00', 40000.0);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);
        MotoRide::factory()->create(['user_id' => $user->id, 'distance' => 160.0, 'started_at' => Carbon::parse('2026-10-01 08:00:00', 'UTC')]);

        $completed = $this->resolve($user);

        $this->assertSame(
            [$previousSport->id, $currentMoto->id, $currentSport->id],
            $completed->map(fn (Challenge $challenge): string => $challenge->id)->all(),
        );
    }

    #[Test]
    public function it_closes_on_the_grace_boundary_with_the_end_of_the_week_when_the_grace_is_zero(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 0]);
        $user = User::factory()->create();
        $accepted = $this->acceptedChallenge($user);
        $proposed = $this->proposedChallenge($user, ChallengeTemplateKey::MotoDistance);
        $this->travelTo(Carbon::parse('2026-10-04 21:59:59', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame(ChallengeStatus::Proposed, $proposed->fresh()->status);

        $this->travelTo(Carbon::parse('2026-10-04 22:00:00', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $accepted->fresh()->status);
        $this->assertSame(ChallengeStatus::Expired, $proposed->fresh()->status);
    }

    #[Test]
    public function it_applies_the_configured_grace_to_the_failure(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 72]);
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->travelTo(Carbon::parse('2026-10-07 21:59:59', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);

        $this->travelTo(Carbon::parse('2026-10-07 22:00:00', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_keeps_the_frozen_closing_when_the_grace_is_lowered_afterwards(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        config(['gamification.challenges.closing_grace_hours' => 0]);
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
    }

    #[Test]
    public function it_keeps_the_frozen_closing_when_the_grace_is_raised_afterwards(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        config(['gamification.challenges.closing_grace_hours' => 168]);
        $this->travelTo(Carbon::parse('2026-10-06 22:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_closes_exactly_at_the_closing_instant_of_the_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->forWeek($this->week)->create([
            'user_id' => $user->id,
            'target_value' => 28,
            'closes_at' => Carbon::parse('2026-10-05 08:00:00', 'UTC'),
        ]);
        $this->travelTo(Carbon::parse('2026-10-05 07:59:59', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);

        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'UTC'));
        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_resolves_without_reading_the_challenge_settings(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        config(['gamification.challenges.closing_grace_hours' => -1, 'gamification.challenges.xp_reward' => 0]);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);

        $completed = $this->resolve($user);

        $this->assertTrue($completed->sole()->is($challenge));
    }

    #[Test]
    public function it_closes_on_the_frozen_bounds_of_the_challenge_when_the_game_timezone_changes(): void
    {
        $user = User::factory()->create();
        $challenge = $this->proposedChallenge($user);
        config(['gamification.timezone' => 'America/New_York']);

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_is_idempotent_across_passes(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-10-01 08:00:00', 30000.0);

        $first = $this->resolve($user);
        $second = $this->resolve($user);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_completes_a_moto_challenge_with_a_ride_recorded_one_second_before_the_closing_instant(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedMotoChallenge($user);
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-06 21:59:59', 160.0);
        $this->travelTo(Carbon::parse('2026-10-06 21:59:59', 'UTC'));

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('160.00', $persisted->current_value);
        $this->assertCount(1, $completed);
    }

    #[Test]
    public function it_fails_a_moto_challenge_whose_ride_was_recorded_at_the_closing_instant(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedMotoChallenge($user);
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-06 22:00:00', 160.0);
        $this->travelTo(Carbon::parse('2026-10-06 22:00:00', 'UTC'));

        $completed = $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Failed, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
        $this->assertCount(0, $completed);
    }

    #[Test]
    public function it_completes_a_moto_challenge_with_a_ride_backdated_inside_the_week_it_was_recorded_in(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedMotoChallenge($user);
        $this->rideAt($user, '2026-09-29 08:00:00', '2026-10-03 10:00:00', 160.0);
        $this->travelTo(Carbon::parse('2026-10-03 10:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_does_not_complete_a_moto_challenge_with_a_ride_recorded_after_the_closing_instant_before_the_failure_pass(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedMotoChallenge($user);
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-06 23:00:00', 160.0);
        $this->travelTo(Carbon::parse('2026-10-06 23:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Failed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_measures_a_moto_challenge_with_its_frozen_closing_instant_when_the_grace_is_raised_afterwards(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedMotoChallenge($user);
        config(['gamification.challenges.closing_grace_hours' => 168]);
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-07 12:00:00', 160.0);
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00', 'UTC'));

        $this->resolve($user);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Failed, $persisted->status);
        $this->assertSame('0.00', $persisted->current_value);
    }

    #[Test]
    public function it_completes_a_sport_challenge_with_an_activity_synchronised_after_the_closing_instant(): void
    {
        $user = User::factory()->create();
        $challenge = $this->acceptedChallenge($user);
        $this->activityAt($user, '2026-10-04 15:00:00', 30000.0, '2026-10-07 01:00:00');
        $this->travelTo(Carbon::parse('2026-10-07 01:00:00', 'UTC'));

        $this->resolve($user);

        $this->assertSame(ChallengeStatus::Completed, $challenge->fresh()->status);
    }

    private function resolve(User $user): Collection
    {
        return $this->app->make(ResolveChallenges::class)->handle($user);
    }

    private function acceptedChallenge(User $user): Challenge
    {
        return Challenge::factory()->accepted()->forWeek($this->week)->create([
            'user_id' => $user->id,
            'target_value' => 28,
            'accepted_at' => Carbon::parse('2026-09-29 08:00:00', 'UTC'),
        ]);
    }

    private function acceptedMotoChallenge(User $user): Challenge
    {
        return Challenge::factory()->accepted()->forWeek($this->week)->forTemplate(ChallengeTemplateKey::MotoDistance)->create([
            'user_id' => $user->id,
            'target_value' => 150,
            'accepted_at' => Carbon::parse('2026-09-29 08:00:00', 'UTC'),
        ]);
    }

    private function proposedChallenge(User $user, ChallengeTemplateKey $template = ChallengeTemplateKey::SportDistance): Challenge
    {
        return Challenge::factory()->forWeek($this->week)->forTemplate($template)->create([
            'user_id' => $user->id,
            'target_value' => 28,
        ]);
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

    private function rideAt(User $user, string $startedAtUtc, string $recordedAtUtc, float $kilometers): MotoRide
    {
        return MotoRide::factory()->create([
            'user_id' => $user->id,
            'distance' => $kilometers,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
            'recorded_at' => Carbon::parse($recordedAtUtc, 'UTC'),
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

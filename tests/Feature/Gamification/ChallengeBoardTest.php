<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\TransitionChallenge;
use Functional\Gamification\Challenges\States\ChallengeState;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Livewire\ChallengeBoard;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeCard;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->app->setLocale('fr');
        Queue::fake();
    }

    #[Test]
    public function it_renders_the_title_and_the_dates_of_the_current_week(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertOk()
            ->assertSee('Défis de la semaine')
            ->assertSee('Semaine du 28 sept. au 4 oct.');
    }

    #[Test]
    public function it_renders_the_title_and_the_dates_of_the_current_week_in_english(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Challenges of the week')
            ->assertSee('Week of Sep 28 to Oct 4');
    }

    #[Test]
    public function it_offers_both_actions_with_accessible_labels_on_a_proposed_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeHtml('aria-label="Relever le défi Distance sportive"')
            ->assertSeeHtml('aria-label="Passer le défi Distance sportive"')
            ->assertSee('Proposé');
    }

    #[Test]
    public function it_translates_the_confirmation_of_the_decline_and_guards_against_double_clicks(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeHtml('wire:confirm="Passer ce défi ? Vous ne pourrez plus le relever cette semaine."')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }

    #[Test]
    public function it_offers_the_actions_in_english(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeHtml('aria-label="Take on the challenge: Sport distance"')
            ->assertSeeHtml('aria-label="Skip the challenge: Sport distance"');
    }

    #[Test]
    public function it_shows_the_progressbar_and_no_action_on_an_accepted_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->accepted()->create(['current_value' => 12, 'target_value' => 28]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeHtml('role="progressbar"')
            ->assertSeeHtml('aria-valuenow="43"')
            ->assertSeeHtml('aria-label="Progression du défi Distance sportive"')
            ->assertSeeHtml('aria-valuetext="12 / 28 km"')
            ->assertSee('En cours')
            ->assertDontSeeHtml('wire:click')
            ->assertDontSeeHtml('<button');
    }

    #[Test]
    public function it_draws_the_bar_and_the_value_from_the_numbers_not_from_the_rounded_label(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->accepted()->create(['current_value' => 27.96, 'target_value' => 28]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeHtml('aria-valuetext="28 / 28 km"')
            ->assertSeeHtml('aria-valuenow="99"')
            ->assertSeeHtml('width: 99.86%')
            ->assertDontSeeHtml('aria-valuenow="100"');
    }

    #[Test]
    public function it_describes_the_challenge_its_typical_week_and_its_reward(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create(['baseline_value' => 25, 'target_value' => 28, 'xp_reward' => 50]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Distance sportive')
            ->assertSee('Parcourez 28 km cette semaine.')
            ->assertSee('Votre semaine type : 25 km')
            ->assertSee('+50 XP');
    }

    #[Test]
    public function it_invites_to_accept_a_proposed_challenge_whose_target_is_already_reached(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create(['current_value' => 30, 'target_value' => 28]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Objectif déjà atteint : relevez le défi pour le valider');
    }

    #[Test]
    public function it_does_not_invite_when_the_proposed_target_is_not_reached(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create(['current_value' => 12, 'target_value' => 28]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertDontSee('Objectif déjà atteint');
    }

    #[Test]
    public function it_shows_when_the_progress_was_last_updated_on_an_open_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->accepted()->create(['updated_at' => now()->subHours(2)]);

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Progression mise à jour il y a 2 heures');
    }

    #[Test]
    public function it_lists_the_cards_by_domain_then_by_template(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::ExplorationCells)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportMovingTime)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::MotoRideCount)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportDistance)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSeeInOrder(['Distance sportive', 'Temps en mouvement', 'Sorties moto', 'Exploration']);
    }

    #[Test]
    public function it_shows_only_the_challenges_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->forTemplate(ChallengeTemplateKey::MotoDistance)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Distance sportive')
            ->assertDontSee('Distance moto');
    }

    #[Test]
    public function it_accepts_a_proposed_challenge_announces_it_and_dispatches_the_run(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('accept', $challenge->id)
            ->assertSet('announcement', 'Défi accepté : Distance sportive')
            ->assertSeeHtml('<div role="status" aria-live="polite" class="sr-only">Défi accepté : Distance sportive</div>')
            ->assertDontSeeHtml('wire:click');

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
        Queue::assertPushed(ProcessUserGamificationJob::class, 1);
    }

    #[Test]
    public function it_declines_a_proposed_challenge_and_announces_it(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('decline', $challenge->id)
            ->assertSet('announcement', 'Défi passé : Distance sportive')
            ->assertSeeHtml('<div role="status" aria-live="polite" class="sr-only">Défi passé : Distance sportive</div>');

        $this->assertSame(ChallengeStatus::Declined, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_announces_in_english(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('accept', $challenge->id)
            ->assertSet('announcement', 'Challenge accepted: Sport distance');
    }

    #[Test]
    public function it_starts_with_an_empty_announcement(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ChallengeBoard::class)
            ->assertSet('announcement', '');
    }

    #[Test]
    public function it_answers_not_found_when_accepting_the_challenge_of_another_user(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondOverHttp(User::factory()->create(), 'accept', $challenge->id)->assertNotFound();

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_answers_not_found_when_declining_the_challenge_of_another_user(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondOverHttp(User::factory()->create(), 'decline', $challenge->id)->assertNotFound();

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_answers_not_found_for_an_unknown_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();

        $this->respondOverHttp($user, 'accept', 'unknown-challenge-id')->assertNotFound();

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_answers_not_found_even_when_the_challenge_of_another_user_is_already_resolved(): void
    {
        $challenge = Challenge::factory()->accepted()->create();

        $this->respondOverHttp(User::factory()->create(), 'decline', $challenge->id)->assertNotFound();

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
    }

    #[Test]
    public function it_answers_conflict_when_accepting_a_challenge_already_accepted(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->accepted()->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('accept', $challenge->id)
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertSet('announcement', '');

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_answers_conflict_when_declining_a_challenge_already_accepted(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->accepted()->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('decline', $challenge->id)
            ->assertStatus(Response::HTTP_CONFLICT);

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
    }

    #[Test]
    public function it_turns_a_lost_race_into_a_conflict_without_announcing_anything(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();
        $this->app->instance(TransitionChallenge::class, new class extends TransitionChallenge
        {
            public function handle(Challenge $challenge, ChallengeState $next, ?float $currentValue = null): void
            {
                throw StaleChallengeStatusException::for($challenge);
            }
        });

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->call('accept', $challenge->id)
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertSet('announcement', '');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_lets_any_other_failure_of_the_response_travel_untouched(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create();
        $this->app->instance(TransitionChallenge::class, new class extends TransitionChallenge
        {
            public function handle(Challenge $challenge, ChallengeState $next, ?float $currentValue = null): void
            {
                throw IllegalChallengeTransitionException::for($challenge->state(), 'accept');
            }
        });

        $this->assertThrows(
            fn () => Livewire::actingAs($user)->test(ChallengeBoard::class)->call('accept', $challenge->id),
            IllegalChallengeTransitionException::class,
        );
    }

    #[Test]
    public function it_renders_no_action_once_the_week_of_a_proposed_challenge_is_over(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();
        $this->travelTo(Carbon::parse('2026-10-04 22:00:00', 'UTC'));

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertDontSeeHtml('wire:click')
            ->assertDontSeeHtml('<button');
    }

    #[Test]
    public function it_moves_the_week_forward_when_the_next_one_starts(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();
        $this->travelTo(Carbon::parse('2026-10-04 22:00:00', 'UTC'));

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Semaine du 5 oct. au 11 oct.')
            ->assertSee('Semaine dernière');
    }

    #[Test]
    public function it_lists_the_verdicts_of_last_week(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->completed()->forWeek($this->lastWeek())->create(['xp_reward' => 50]);
        Challenge::factory()->for($user)->failed()->forWeek($this->lastWeek())->forTemplate(ChallengeTemplateKey::SportActivityCount)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Semaine dernière')
            ->assertSee('Réussi')
            ->assertSee('Manqué')
            ->assertSee('Distance sportive')
            ->assertSee('Activités sportives')
            ->assertSee('+50 XP')
            ->assertDontSee('Clôture en attente des dernières synchronisations');
    }

    #[Test]
    public function it_announces_that_the_closing_waits_for_the_latest_syncs_while_last_week_is_still_open(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->accepted()->forWeek($this->lastWeek())->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Semaine dernière')
            ->assertSee('Clôture en attente des dernières synchronisations')
            ->assertDontSeeHtml('wire:click');
    }

    #[Test]
    public function it_does_not_announce_a_pending_closing_on_a_proposed_challenge_of_last_week(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forWeek($this->lastWeek())->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Semaine dernière')
            ->assertSee('Proposé')
            ->assertDontSee('Clôture en attente des dernières synchronisations');
    }

    #[Test]
    public function it_hides_the_last_week_section_when_there_was_no_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertDontSee('Semaine dernière');
    }

    #[Test]
    public function it_does_not_list_older_weeks(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->completed()->forWeek($this->lastWeek()->previous())->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertDontSee('Semaine dernière')
            ->assertDontSee('Réussi');
    }

    #[Test]
    public function it_shows_a_neutral_empty_state_with_the_history_window(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ChallengeBoard::class)
            ->assertSee('Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de vos 4 dernières semaines d\'activité.');
    }

    #[Test]
    public function it_feeds_the_empty_state_with_the_configured_history_window(): void
    {
        config(['gamification.challenges.history_weeks' => 6]);

        Livewire::actingAs(User::factory()->create())
            ->test(ChallengeBoard::class)
            ->assertSee('vos 6 dernières semaines');
    }

    #[Test]
    public function it_shows_the_empty_state_in_english(): void
    {
        $this->app->setLocale('en');

        Livewire::actingAs(User::factory()->create())
            ->test(ChallengeBoard::class)
            ->assertSee('No challenges this week. Challenges are proposed every Monday from your last 4 weeks of activity.');
    }

    #[Test]
    public function it_hides_the_empty_state_once_the_week_has_challenges(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertDontSee('Aucun défi cette semaine');
    }

    #[Test]
    public function it_keeps_the_empty_state_of_this_week_next_to_the_verdicts_of_last_week(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->completed()->forWeek($this->lastWeek())->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertSee('Aucun défi cette semaine')
            ->assertSee('Semaine dernière');
    }

    #[Test]
    public function it_hands_one_card_per_challenge_with_the_respondable_flag_to_the_view(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::MotoDistance)->accepted()->create();

        Livewire::actingAs($user)
            ->test(ChallengeBoard::class)
            ->assertViewHas('currentCards', fn (Collection $cards): bool => $cards->map(
                fn (ChallengeCard $card): array => [$card->template, $card->isRespondable]
            )->all() === [[ChallengeTemplateKey::SportDistance, true], [ChallengeTemplateKey::MotoDistance, false]]);
    }

    #[Test]
    public function it_renders_a_board_of_challenges_with_a_constant_number_of_queries(): void
    {
        $user = User::factory()->create();
        foreach ([ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::MotoDistance, ChallengeTemplateKey::ExplorationCells] as $template) {
            Challenge::factory()->for($user)->forTemplate($template)->create();
        }
        $queriesWithThreeChallenges = $this->queriesOfBoardFor($user);
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportElevation)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportMovingTime)->create();

        $this->assertSame($queriesWithThreeChallenges, $this->queriesOfBoardFor($user));
    }

    private function respondOverHttp(User $user, string $method, string $challengeId): TestResponse
    {
        $snapshot = Livewire::actingAs($user)->test(ChallengeBoard::class)->snapshot;

        return $this->actingAs($user, 'web')->postJson(route('default.livewire.update'), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => [],
                'calls' => [['path' => '', 'method' => $method, 'params' => [$challengeId]]],
            ]],
        ], ['X-Livewire' => 'true']);
    }

    private function queriesOfBoardFor(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($user)->test(ChallengeBoard::class);

        return count(DB::getQueryLog());
    }

    private function lastWeek(): GamificationWeek
    {
        return $this->app->make(GamificationCalendar::class)->currentWeek()->previous();
    }
}

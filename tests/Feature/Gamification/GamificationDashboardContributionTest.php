<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Dashboard\GamificationDashboardContribution;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-10 12:00', 'Europe/Paris')->utc());
    }

    #[Test]
    public function it_summarises_the_player_level_and_xp(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'total_xp' => 3000, 'level' => 7]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('gamification', $summary->key);
        $this->assertTrue($summary->available);
        $this->assertSame('7', $summary->metricValue);
        $this->assertNotEmpty($summary->secondaryLines);
    }

    #[Test]
    public function it_reports_level_one_for_a_user_without_profile(): void
    {
        $user = User::factory()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('1', $summary->metricValue);
    }

    #[Test]
    public function it_exposes_a_navigation_item_towards_the_player_page(): void
    {
        $item = $this->app->make(GamificationDashboardContribution::class)->navigationItem();

        $this->assertSame('player', $item->route);
    }

    #[Test]
    public function it_surfaces_the_hottest_streak_on_the_summary(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'current_count' => 3]);
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Todo, 'current_count' => 9]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Série Tâches : 9 j', $summary->secondaryLines);
    }

    #[Test]
    public function it_omits_the_streak_line_without_a_live_streak(): void
    {
        $user = User::factory()->create();
        Streak::factory()->broken()->create(['user_id' => $user->id]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertCount(4, $summary->secondaryLines);
    }

    #[Test]
    public function it_ignores_a_projection_whose_last_activity_is_two_days_old(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Sport,
            'current_count' => 12,
            'last_activity_date' => now()->subDays(2)->toDateString(),
        ]);
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Todo,
            'current_count' => 4,
            'last_activity_date' => now()->subDay()->toDateString(),
        ]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Série Tâches : 4 j', $summary->secondaryLines);
    }

    #[Test]
    public function it_translates_the_summary_in_english(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();
        $this->app->make(SyncBadgeCatalogue::class)->handle();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Todo,
            'current_count' => 9,
            'last_activity_date' => now()->toDateString(),
        ]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('Player', $summary->title);
        $this->assertSame('lvl', $summary->metricUnit);
        $this->assertContains('0 XP in total', $summary->secondaryLines);
        $this->assertContains('Tasks streak: 9 d', $summary->secondaryLines);
        $this->assertContains('Badges: 0 / 30', $summary->secondaryLines);
    }

    #[Test]
    public function it_shows_the_earned_and_total_badges_on_the_summary(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->app->make(SyncBadgeCatalogue::class)->handle();
        BadgeAward::factory()->for($user)->for(Badge::query()->where('key', BadgeRuleKey::SportDistance->badgeKey(BadgeTier::Bronze))->sole())->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Badges : 1 / 30', $summary->secondaryLines);
    }

    #[Test]
    public function it_shows_zero_of_zero_badges_with_an_empty_catalogue(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Badges : 0 / 0', $summary->secondaryLines);
    }

    #[Test]
    public function it_announces_the_proposed_challenges_to_take_on_this_week(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::MotoDistance)->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('2 défis à relever cette semaine', $summary->secondaryLines);
    }

    #[Test]
    public function it_uses_the_singular_for_a_single_proposed_challenge(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('1 défi à relever cette semaine', $summary->secondaryLines);
    }

    #[Test]
    public function it_counts_the_completed_over_the_committed_challenges_once_no_proposal_is_left(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->completed()->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($user)->accepted()->forTemplate(ChallengeTemplateKey::MotoDistance)->create();
        Challenge::factory()->for($user)->declined()->forTemplate(ChallengeTemplateKey::ExplorationCells)->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Défis : 1 / 2 réussis', $summary->secondaryLines);
        $this->assertNotContains('1 défi à relever cette semaine', $summary->secondaryLines);
    }

    #[Test]
    public function it_prefers_the_pending_line_while_a_proposal_is_left(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->completed()->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::MotoDistance)->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('1 défi à relever cette semaine', $summary->secondaryLines);
        $this->assertCount(1, $this->challengeLines($summary->secondaryLines));
    }

    #[Test]
    public function it_adds_no_challenge_line_without_challenges_this_week(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame([], $this->challengeLines($summary->secondaryLines));
    }

    #[Test]
    public function it_adds_no_challenge_line_when_every_challenge_was_skipped(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        Challenge::factory()->for($user)->declined()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame([], $this->challengeLines($summary->secondaryLines));
    }

    #[Test]
    public function it_ignores_the_challenges_of_other_weeks_and_other_users(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $previousWeek = $this->app->make(GamificationCalendar::class)->currentWeek()->previous();
        Challenge::factory()->for($user)->forWeek($previousWeek)->create();
        Challenge::factory()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame([], $this->challengeLines($summary->secondaryLines));
    }

    #[Test]
    public function it_translates_the_challenge_lines_in_english(): void
    {
        $this->app->setLocale('en');
        $pendingUser = User::factory()->create();
        Challenge::factory()->for($pendingUser)->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($pendingUser)->forTemplate(ChallengeTemplateKey::MotoDistance)->create();
        $committedUser = User::factory()->create();
        Challenge::factory()->for($committedUser)->completed()->forTemplate(ChallengeTemplateKey::SportDistance)->create();
        Challenge::factory()->for($committedUser)->accepted()->forTemplate(ChallengeTemplateKey::MotoDistance)->create();

        $contribution = $this->app->make(GamificationDashboardContribution::class);

        $this->assertContains('2 challenges to take on this week', $contribution->dashboardSummary($pendingUser)->secondaryLines);
        $this->assertContains('Challenges: 1 / 2 completed', $contribution->dashboardSummary($committedUser)->secondaryLines);
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function challengeLines(array $lines): array
    {
        return array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'Défis') || str_contains($line, 'défi') || str_contains($line, 'hallenge')));
    }
}

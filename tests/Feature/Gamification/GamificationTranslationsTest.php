<?php

namespace Tests\Feature\Gamification;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationTranslationsTest extends TestCase
{
    #[Test]
    public function it_ships_the_same_keys_in_french_and_english(): void
    {
        $directory = base_path('functional/gamification/lang');

        foreach (glob("{$directory}/fr/*.php") ?: [] as $frenchFile) {
            $englishFile = "{$directory}/en/".basename($frenchFile);

            $this->assertFileExists($englishFile);
            $this->assertSame(
                array_keys(Arr::dot(require $frenchFile)),
                array_keys(Arr::dot(require $englishFile)),
                basename($frenchFile),
            );
        }
    }

    #[Test]
    public function it_resolves_the_layer_namespace(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Joueur', __('gamification::player.title'));
        $this->assertSame('Record : 12', __('gamification::player.streak_best', ['count' => 12]));
    }

    #[Test]
    public function it_keeps_the_badge_dashboard_line_in_the_dashboard_file(): void
    {
        $this->app->setLocale('en');

        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/en/badges.php'));
        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/fr/badges.php'));
        $this->assertSame('Badges: 3 / 30', __('gamification::dashboard.badges', ['earned' => 3, 'total' => 30]));
    }

    #[Test]
    public function it_keeps_the_challenge_dashboard_lines_in_the_dashboard_file(): void
    {
        $this->app->setLocale('en');

        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/en/challenges.php'));
        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/fr/challenges.php'));
        $this->assertSame('Challenges: 1 / 2 completed', __('gamification::dashboard.challenges_progress', ['completed' => 1, 'committed' => 2]));
        $this->assertSame('1 challenge to take on this week', trans_choice('gamification::dashboard.challenges_pending', 1));
        $this->assertSame('2 challenges to take on this week', trans_choice('gamification::dashboard.challenges_pending', 2));
    }

    #[Test]
    public function it_agrees_the_french_challenge_dashboard_lines_with_their_count(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Défis : 1 / 2 réussis', __('gamification::dashboard.challenges_progress', ['completed' => 1, 'committed' => 2]));
        $this->assertSame('1 défi à relever cette semaine', trans_choice('gamification::dashboard.challenges_pending', 1));
        $this->assertSame('2 défis à relever cette semaine', trans_choice('gamification::dashboard.challenges_pending', 2));
    }

    #[Test]
    public function it_translates_the_challenge_statuses_in_both_locales(): void
    {
        $statuses = ['fr' => ['Proposé', 'En cours', 'Réussi', 'Manqué', 'Passé', 'Expiré'], 'en' => ['Proposed', 'In progress', 'Completed', 'Missed', 'Skipped', 'Expired']];

        foreach ($statuses as $locale => $labels) {
            $this->app->setLocale($locale);

            foreach (['proposed', 'accepted', 'completed', 'failed', 'declined', 'expired'] as $position => $status) {
                $this->assertSame($labels[$position], __("gamification::challenges.statuses.{$status}"), "{$locale}.{$status}");
            }
        }
    }

    #[Test]
    public function it_agrees_the_challenge_notification_titles_with_their_count(): void
    {
        $this->app->setLocale('fr');
        $this->assertSame('1 nouveau défi cette semaine', trans_choice('gamification::challenges.notification.proposed_title', 1));
        $this->assertSame('3 nouveaux défis cette semaine', trans_choice('gamification::challenges.notification.proposed_title', 3));
        $this->assertSame('Défi réussi : Distance sportive', __('gamification::challenges.notification.completed_title', ['name' => 'Distance sportive']));
        $this->assertSame('Vous avez atteint 28 km et gagné 50 XP.', __('gamification::challenges.notification.completed_body', ['target' => '28 km', 'xp' => 50]));

        $this->app->setLocale('en');
        $this->assertSame('1 new challenge this week', trans_choice('gamification::challenges.notification.proposed_title', 1));
        $this->assertSame('3 new challenges this week', trans_choice('gamification::challenges.notification.proposed_title', 3));
        $this->assertSame('Challenge completed: Sport distance', __('gamification::challenges.notification.completed_title', ['name' => 'Sport distance']));
    }

    #[Test]
    public function it_translates_the_not_respondable_error_in_both_locales(): void
    {
        $this->app->setLocale('fr');
        $this->assertSame('Ce défi ne peut plus être accepté ni refusé.', __('gamification::challenges.errors.not_respondable'));

        $this->app->setLocale('en');
        $this->assertSame('This challenge can no longer be accepted or skipped.', __('gamification::challenges.errors.not_respondable'));
    }

    #[Test]
    public function it_resolves_every_text_the_challenge_board_needs_in_both_locales(): void
    {
        $boardKeys = [
            'title', 'subtitle', 'week_date_format', 'baseline', 'reward', 'progress', 'progress_label', 'updated',
            'accept', 'accept_label', 'decline', 'decline_label', 'decline_confirm', 'already_reached',
            'previous_title', 'grace_pending', 'empty', 'accepted_announcement', 'declined_announcement',
        ];

        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach ($boardKeys as $boardKey) {
                $this->assertNotSame("gamification::challenges.board.{$boardKey}", __("gamification::challenges.board.{$boardKey}"), "{$locale}.{$boardKey}");
            }
        }
    }

    #[Test]
    public function it_composes_the_challenge_board_texts_in_french(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Défis de la semaine', __('gamification::challenges.board.title'));
        $this->assertSame('Semaine du 28 sept. au 4 oct.', __('gamification::challenges.board.subtitle', ['start' => '28 sept.', 'end' => '4 oct.']));
        $this->assertSame('Votre semaine type : 25 km', __('gamification::challenges.board.baseline', ['baseline' => '25 km']));
        $this->assertSame('+50 XP', __('gamification::challenges.board.reward', ['xp' => 50]));
        $this->assertSame('12 / 28 km', __('gamification::challenges.board.progress', ['current' => '12', 'target' => '28 km']));
        $this->assertSame('Relever le défi Distance sportive', __('gamification::challenges.board.accept_label', ['name' => 'Distance sportive']));
        $this->assertSame('Passer le défi Distance sportive', __('gamification::challenges.board.decline_label', ['name' => 'Distance sportive']));
        $this->assertSame('Défi accepté : Distance sportive', __('gamification::challenges.board.accepted_announcement', ['name' => 'Distance sportive']));
        $this->assertSame('Défi passé : Distance sportive', __('gamification::challenges.board.declined_announcement', ['name' => 'Distance sportive']));
        $this->assertSame('Semaine dernière', __('gamification::challenges.board.previous_title'));
        $this->assertSame('Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de vos 4 dernières semaines d\'activité.', trans_choice('gamification::challenges.board.empty', 4, ['weeks' => 4]));
        $this->assertSame('Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de votre dernière semaine d\'activité.', trans_choice('gamification::challenges.board.empty', 1, ['weeks' => 1]));
    }

    #[Test]
    public function it_composes_the_challenge_board_texts_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Challenges of the week', __('gamification::challenges.board.title'));
        $this->assertSame('Take on the challenge: Sport distance', __('gamification::challenges.board.accept_label', ['name' => 'Sport distance']));
        $this->assertSame('Skip the challenge: Sport distance', __('gamification::challenges.board.decline_label', ['name' => 'Sport distance']));
        $this->assertSame('Challenge accepted: Sport distance', __('gamification::challenges.board.accepted_announcement', ['name' => 'Sport distance']));
        $this->assertSame('No challenges this week. Challenges are proposed every Monday from your last 6 weeks of activity.', trans_choice('gamification::challenges.board.empty', 6, ['weeks' => 6]));
        $this->assertSame('No challenges this week. Challenges are proposed every Monday from your last week of activity.', trans_choice('gamification::challenges.board.empty', 1, ['weeks' => 1]));
    }

    #[Test]
    public function it_names_each_action_of_the_board_with_the_text_it_displays_in_both_locales(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            $this->assertStringContainsString(__('gamification::challenges.board.accept'), __('gamification::challenges.board.accept_label', ['name' => 'Sport']), "{$locale}.accept");
            $this->assertStringContainsString(__('gamification::challenges.board.decline'), __('gamification::challenges.board.decline_label', ['name' => 'Sport']), "{$locale}.decline");
        }
    }

    #[Test]
    public function it_formats_the_week_dates_of_the_board_in_both_locales(): void
    {
        $monday = Carbon::parse('2026-09-28', 'Europe/Paris');
        $sunday = Carbon::parse('2026-10-04', 'Europe/Paris');

        $this->app->setLocale('fr');
        $frenchFormat = __('gamification::challenges.board.week_date_format');
        $this->assertSame('28 sept.', $monday->translatedFormat($frenchFormat));
        $this->assertSame('4 oct.', $sunday->translatedFormat($frenchFormat));

        $this->app->setLocale('en');
        $englishFormat = __('gamification::challenges.board.week_date_format');
        $this->assertSame('Sep 28', $monday->translatedFormat($englishFormat));
        $this->assertSame('Oct 4', $sunday->translatedFormat($englishFormat));
    }
}

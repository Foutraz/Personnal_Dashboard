<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Notifications\ChallengeCompletedNotification;
use Functional\Gamification\Notifications\ChallengesProposedNotification;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private GamificationWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-09-30 12:00:00', 'UTC'));
    }

    #[Test]
    public function it_delivers_the_proposed_notification_through_the_database_channel_only(): void
    {
        $notification = new ChallengesProposedNotification($this->week, 3);

        $this->assertSame(['database'], $notification->via(User::factory()->create()));
    }

    #[Test]
    public function it_delivers_the_completed_notification_through_the_database_channel_only(): void
    {
        $notification = new ChallengeCompletedNotification(Challenge::factory()->completed()->create());

        $this->assertSame(['database'], $notification->via(User::factory()->create()));
    }

    #[Test]
    public function it_never_queues_the_proposed_notification_so_it_stays_atomic_with_the_challenges(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new ChallengesProposedNotification($this->week, 1));
    }

    #[Test]
    public function it_never_queues_the_completed_notification_so_it_stays_atomic_with_the_challenge(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new ChallengeCompletedNotification(Challenge::factory()->completed()->create()));
    }

    #[Test]
    public function it_announces_three_new_challenges_in_french(): void
    {
        $this->app->setLocale('fr');

        $data = (new ChallengesProposedNotification($this->week, 3))->toArray(User::factory()->create());

        $this->assertSame([
            'title' => '3 nouveaux défis cette semaine',
            'body' => 'Découvrez vos défis de la semaine et relevez ceux qui vous motivent.',
            'week_key' => '2026-W40',
            'count' => 3,
        ], $data);
    }

    #[Test]
    public function it_announces_a_single_new_challenge_in_the_singular_in_french(): void
    {
        $this->app->setLocale('fr');

        $data = (new ChallengesProposedNotification($this->week, 1))->toArray(User::factory()->create());

        $this->assertSame('1 nouveau défi cette semaine', $data['title']);
        $this->assertSame(1, $data['count']);
    }

    #[Test]
    public function it_announces_the_new_challenges_in_english(): void
    {
        $this->app->setLocale('en');

        $notification = new ChallengesProposedNotification($this->week, 3);
        $single = new ChallengesProposedNotification($this->week, 1);

        $this->assertSame('3 new challenges this week', $notification->toArray(User::factory()->create())['title']);
        $this->assertSame('1 new challenge this week', $single->toArray(User::factory()->create())['title']);
    }

    #[Test]
    public function it_describes_the_completed_challenge_in_french(): void
    {
        $this->app->setLocale('fr');
        $challenge = Challenge::factory()->completed()->create(['target_value' => 28, 'xp_reward' => 50]);

        $data = (new ChallengeCompletedNotification($challenge))->toArray($challenge->user);

        $this->assertSame([
            'title' => 'Défi réussi : Distance sportive',
            'body' => 'Vous avez atteint 28 km et gagné 50 XP.',
            'challenge_id' => $challenge->id,
            'template_key' => 'sport_distance',
            'xp_reward' => 50,
        ], $data);
    }

    #[Test]
    public function it_describes_the_completed_challenge_in_english(): void
    {
        $this->app->setLocale('en');
        $challenge = Challenge::factory()->completed()->forTemplate(ChallengeTemplateKey::SportActivityCount)->create(['target_value' => 3, 'xp_reward' => 80]);

        $data = (new ChallengeCompletedNotification($challenge))->toArray($challenge->user);

        $this->assertSame('Challenge completed: Sport activities', $data['title']);
        $this->assertSame('You reached 3 and earned 80 XP.', $data['body']);
        $this->assertSame('sport_activity_count', $data['template_key']);
        $this->assertSame(80, $data['xp_reward']);
    }

    #[Test]
    public function it_stores_both_notifications_as_database_rows_typed_by_class(): void
    {
        $challenge = Challenge::factory()->completed()->create();
        $user = $challenge->user;

        $user->notify(new ChallengesProposedNotification($this->week, 2));
        $user->notify(new ChallengeCompletedNotification($challenge));

        $this->assertSame(1, $user->notifications()->where('type', ChallengesProposedNotification::class)->count());
        $this->assertSame(1, $user->notifications()->where('type', ChallengeCompletedNotification::class)->count());
    }
}

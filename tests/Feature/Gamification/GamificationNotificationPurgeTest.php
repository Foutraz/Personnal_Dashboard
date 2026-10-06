<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Gamification\Notifications\ChallengeCompletedNotification;
use Functional\Gamification\Notifications\ChallengesProposedNotification;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationNotificationPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function notifyBadgeAndChallenges(User $user): void
    {
        $award = BadgeAward::factory()->for($user)->create();
        $challenge = Challenge::factory()->completed()->for($user)->create();

        $user->notify(new BadgeAwardedNotification($award->badge));
        $user->notify(new ChallengesProposedNotification(app(GamificationCalendar::class)->currentWeek(), 2));
        $user->notify(new ChallengeCompletedNotification($challenge));
    }

    #[Test]
    public function it_purges_the_badge_and_challenge_notifications_of_a_deleted_user_one_row_at_a_time(): void
    {
        $user = User::factory()->create();
        $this->notifyBadgeAndChallenges($user);
        $deletedNotificationCount = 0;
        DatabaseNotification::deleted(function () use (&$deletedNotificationCount): void {
            $deletedNotificationCount++;
        });

        $user->delete();

        $this->assertSame(3, $deletedNotificationCount);
        $this->assertSame(0, DatabaseNotification::query()->whereMorphedTo('notifiable', $user)->count());
    }

    #[Test]
    public function it_keeps_the_badge_and_challenge_notifications_of_the_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->notifyBadgeAndChallenges($user);
        $this->notifyBadgeAndChallenges($otherUser);

        $user->delete();

        $this->assertSame(3, DatabaseNotification::query()->whereMorphedTo('notifiable', $otherUser)->count());
    }
}

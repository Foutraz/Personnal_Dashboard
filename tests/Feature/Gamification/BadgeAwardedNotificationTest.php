<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeAwardedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_delivered_through_the_database_channel_only(): void
    {
        $notification = new BadgeAwardedNotification(Badge::factory()->create());

        $this->assertSame(['database'], $notification->via(User::factory()->create()));
    }

    #[Test]
    public function it_describes_the_badge_in_french(): void
    {
        $this->app->setLocale('fr');
        $badge = Badge::factory()->create(['rule_key' => 'sport_distance', 'tier' => BadgeTier::Silver, 'xp_reward' => 150]);

        $data = (new BadgeAwardedNotification($badge))->toArray(User::factory()->create());

        $this->assertSame([
            'title' => 'Nouveau badge : Distance sportive (Argent)',
            'body' => 'Vous avez obtenu le badge Distance sportive (Argent) et gagné 150 XP.',
            'badge_key' => $badge->key,
            'tier' => 'silver',
            'xp_reward' => 150,
        ], $data);
    }

    #[Test]
    public function it_describes_the_badge_in_english(): void
    {
        $this->app->setLocale('en');
        $badge = Badge::factory()->create(['rule_key' => 'moto_distance', 'tier' => BadgeTier::Gold, 'xp_reward' => 500]);

        $data = (new BadgeAwardedNotification($badge))->toArray(User::factory()->create());

        $this->assertSame('New badge: Motorbike distance (Gold)', $data['title']);
        $this->assertSame('You earned the Motorbike distance badge (Gold) and gained 500 XP.', $data['body']);
    }
}

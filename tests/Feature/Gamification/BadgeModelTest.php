<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_badge_and_its_award_with_casts(): void
    {
        $award = BadgeAward::factory()->create();

        $badge = $award->badge;

        $this->assertInstanceOf(GamificationDomain::class, $badge->domain);
        $this->assertInstanceOf(BadgeTier::class, $badge->tier);
        $this->assertTrue($badge->awards->contains($award));
        $this->assertTrue($award->user->is(User::query()->findOrFail($award->user_id)));
        $this->assertNotNull($award->awarded_at);
    }

    #[Test]
    public function it_enforces_a_unique_badge_key(): void
    {
        $badge = Badge::factory()->create();

        $this->expectException(QueryException::class);

        Badge::factory()->create(['key' => $badge->key]);
    }

    #[Test]
    public function it_ignores_a_duplicate_award_for_the_same_user_and_badge(): void
    {
        $award = BadgeAward::factory()->create();

        $inserted = DB::table('badge_awards')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'user_id' => $award->user_id,
            'badge_id' => $award->badge_id,
            'awarded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $inserted);
        $this->assertSame(1, BadgeAward::query()->count());
    }

    #[Test]
    public function it_translates_the_name_and_description(): void
    {
        $this->app->setLocale('fr');
        $badge = Badge::factory()->create([
            'rule_key' => BadgeRuleKey::SportDistance->value,
            'tier' => BadgeTier::Bronze,
            'threshold' => 100,
        ]);

        $this->assertSame('Distance sportive', $badge->name());
        $this->assertStringContainsString('100', $badge->description());
    }

    #[Test]
    public function it_groups_the_description_threshold_digits_the_french_way(): void
    {
        $this->app->setLocale('fr');
        $badge = Badge::factory()->create([
            'rule_key' => BadgeRuleKey::SportDistance->value,
            'tier' => BadgeTier::Silver,
            'threshold' => 1000,
        ]);

        $this->assertSame('Cumulez 1 000 km en activité sportive.', Str::squish($badge->description()));
    }

    #[Test]
    public function it_groups_the_description_threshold_digits_the_english_way(): void
    {
        $this->app->setLocale('en');
        $badge = Badge::factory()->create([
            'rule_key' => BadgeRuleKey::SportDistance->value,
            'tier' => BadgeTier::Silver,
            'threshold' => 1000,
        ]);

        $this->assertSame('Cover 1,000 km in sport activities.', $badge->description());
    }

    #[Test]
    public function it_deletes_the_awards_and_badge_notifications_but_keeps_the_catalogue_when_the_user_is_deleted(): void
    {
        $award = BadgeAward::factory()->create();
        $user = $award->user;
        $user->notify(new BadgeAwardedNotification($award->badge));
        $this->assertSame(1, DatabaseNotification::query()->count());

        $user->delete();

        $this->assertSame(0, BadgeAward::query()->count());
        $this->assertSame(0, DatabaseNotification::query()->count());
        $this->assertTrue(Badge::query()->whereKey($award->badge_id)->exists());
    }

    #[Test]
    public function it_keeps_the_badge_notifications_of_the_other_users_when_a_user_is_deleted(): void
    {
        $award = BadgeAward::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->notify(new BadgeAwardedNotification($award->badge));

        $award->user->delete();

        $this->assertSame(1, DatabaseNotification::query()->whereMorphedTo('notifiable', $otherUser)->count());
    }
}

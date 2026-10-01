<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Services\BadgeShowcase;
use Functional\Gamification\Services\Dto\BadgeFamilyProgress;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeFamilyCardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_family_name_its_medals_and_its_progress(): void
    {
        $this->app->setLocale('en');
        $family = $this->family(150, awardedTiers: [BadgeTier::Bronze]);

        $view = $this->blade('<x-gamification::badge-family-card :family="$family" />', ['family' => $family]);

        $view->assertSee('Sport distance');
        $view->assertSee('aria-label="Bronze: Earned"', false);
        $view->assertSee('aria-label="Silver: Locked"', false);
        $view->assertSee('role="progressbar"', false);
        $view->assertSee('Current: 150 km');
        $view->assertSee('Next tier: 1,000 km');
    }

    #[Test]
    public function it_replaces_the_progress_bar_with_the_reached_state_while_an_award_is_pending(): void
    {
        $this->app->setLocale('en');
        $family = $this->family(1500, awardedTiers: [BadgeTier::Bronze]);

        $view = $this->blade('<x-gamification::badge-family-card :family="$family" />', ['family' => $family]);

        $view->assertSee('Reached — unlocks at the next update');
        $view->assertDontSee('role="progressbar"', false);
    }

    #[Test]
    public function it_marks_a_completed_family(): void
    {
        $this->app->setLocale('en');
        $family = $this->family(6000, awardedTiers: BadgeTier::cases());

        $view = $this->blade('<x-gamification::badge-family-card :family="$family" />', ['family' => $family]);

        $view->assertSee('Family completed');
    }

    /**
     * @param  array<int, BadgeTier>  $awardedTiers
     */
    private function family(int $kilometres, array $awardedTiers): BadgeFamilyProgress
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => $kilometres * 1000]);
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        foreach ($awardedTiers as $tier) {
            BadgeAward::factory()->for($user)->for(Badge::query()->where('key', BadgeRuleKey::SportDistance->badgeKey($tier))->sole())->create();
        }

        return $this->app->make(BadgeShowcase::class)
            ->families($user)
            ->sole(fn (BadgeFamilyProgress $family): bool => $family->ruleKey === BadgeRuleKey::SportDistance);
    }
}

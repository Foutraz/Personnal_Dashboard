<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Badges\Rules\SportDistanceBadgeRule;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Services\Dto\BadgeMedal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeMedalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_describes_the_medal_with_the_french_grouped_threshold(): void
    {
        $this->app->setLocale('fr');

        $medal = $this->medal(1000);

        $this->assertSame('Cumulez 1 000 km en activité sportive.', Str::squish($medal->description));
    }

    #[Test]
    public function it_describes_the_medal_with_the_english_grouped_threshold(): void
    {
        $this->app->setLocale('en');

        $medal = $this->medal(1000);

        $this->assertSame('Cover 1,000 km in sport activities.', $medal->description);
    }

    #[Test]
    public function it_formats_the_description_and_the_threshold_label_with_the_same_digits(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            $medal = $this->medal(1000);
            $groupedThreshold = Str::squish(BadgeUnit::Kilometers->formatNumber(1000.0));

            $this->assertStringContainsString($groupedThreshold, Str::squish($medal->description), $locale);
            $this->assertStringContainsString($groupedThreshold, Str::squish($medal->thresholdLabel), $locale);
        }
    }

    #[Test]
    public function it_marks_a_reached_unearned_medal_as_pending(): void
    {
        $medal = $this->medal(100, earned: false, measure: 150.0);

        $this->assertFalse($medal->earned);
        $this->assertTrue($medal->pending);
    }

    private function medal(int $threshold, bool $earned = false, float $measure = 0.0): BadgeMedal
    {
        $badge = Badge::factory()->create([
            'rule_key' => BadgeRuleKey::SportDistance->value,
            'tier' => BadgeTier::Silver,
            'threshold' => $threshold,
        ]);

        return BadgeMedal::fromBadge($badge, new SportDistanceBadgeRule, $earned, $measure);
    }
}

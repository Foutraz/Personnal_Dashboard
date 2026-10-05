<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeRuleKeyTest extends TestCase
{
    #[Test]
    public function it_covers_exactly_the_tagged_badge_rules(): void
    {
        $taggedKeys = collect($this->app->tagged(BadgeRule::TAG))->map(fn (BadgeRule $rule): BadgeRuleKey => $rule->key());

        $this->assertEqualsCanonicalizing(BadgeRuleKey::cases(), $taggedKeys->all());
        $this->assertCount(count(BadgeRuleKey::cases()), $taggedKeys->unique());
    }

    #[Test]
    public function it_configures_a_threshold_for_every_key_and_tier(): void
    {
        foreach (BadgeRuleKey::cases() as $ruleKey) {
            foreach (BadgeTier::cases() as $tier) {
                $this->assertNotNull(config($ruleKey->thresholdConfigPath($tier)), "{$ruleKey->value}.{$tier->value}");
            }
        }
    }

    #[Test]
    public function it_builds_the_config_paths_of_its_thresholds(): void
    {
        $this->assertSame('gamification.badges.thresholds.sport_distance', BadgeRuleKey::SportDistance->thresholdsConfigPath());
        $this->assertSame('gamification.badges.thresholds.todo_streak.gold', BadgeRuleKey::TodoStreak->thresholdConfigPath(BadgeTier::Gold));
    }

    #[Test]
    public function it_builds_the_catalogue_key_of_a_tier(): void
    {
        $this->assertSame('sport_distance_gold', BadgeRuleKey::SportDistance->badgeKey(BadgeTier::Gold));
        $this->assertSame('exploration_cells_bronze', BadgeRuleKey::ExplorationCells->badgeKey(BadgeTier::Bronze));
    }

    #[Test]
    public function it_translates_the_family_label_in_both_locales(): void
    {
        foreach (['fr' => 'Distance sportive', 'en' => 'Sport distance'] as $locale => $label) {
            $this->app->setLocale($locale);

            $this->assertSame($label, BadgeRuleKey::SportDistance->label());
        }
    }

    #[Test]
    public function it_translates_every_label_and_description_in_both_locales(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach (BadgeRuleKey::cases() as $ruleKey) {
                $this->assertStringNotContainsString('gamification::', $ruleKey->label(), "{$locale}.{$ruleKey->value}.name");
                $this->assertStringNotContainsString('gamification::', $ruleKey->description('12'), "{$locale}.{$ruleKey->value}.description");
                $this->assertStringContainsString('12', $ruleKey->description('12'), "{$locale}.{$ruleKey->value}.threshold");
            }
        }
    }

    #[Test]
    public function it_describes_the_family_with_the_formatted_threshold(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Cover 1,000 km in sport activities.', BadgeRuleKey::SportDistance->description('1,000'));
    }
}

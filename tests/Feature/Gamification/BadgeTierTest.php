<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeTierTest extends TestCase
{
    #[Test]
    public function it_labels_every_tier_in_french(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Bronze', BadgeTier::Bronze->label());
        $this->assertSame('Argent', BadgeTier::Silver->label());
        $this->assertSame('Or', BadgeTier::Gold->label());
    }

    #[Test]
    public function it_labels_every_tier_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Silver', BadgeTier::Silver->label());
        $this->assertSame('Gold', BadgeTier::Gold->label());
    }

    #[Test]
    public function it_orders_the_tiers_by_rank(): void
    {
        $this->assertSame(1, BadgeTier::Bronze->rank());
        $this->assertSame(2, BadgeTier::Silver->rank());
        $this->assertSame(3, BadgeTier::Gold->rank());
    }

    #[Test]
    public function it_reads_the_xp_reward_from_the_configuration(): void
    {
        $this->assertSame(50, BadgeTier::Bronze->xpReward());
        $this->assertSame(150, BadgeTier::Silver->xpReward());
        $this->assertSame(500, BadgeTier::Gold->xpReward());

        config(['gamification.badges.tier_xp.gold' => 999]);

        $this->assertSame(999, BadgeTier::Gold->xpReward());
    }

    #[Test]
    public function it_exposes_a_hex_accent_for_every_tier(): void
    {
        foreach (BadgeTier::cases() as $tier) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $tier->accent());
        }
    }

    #[Test]
    public function it_configures_three_thresholds_for_every_family(): void
    {
        $thresholds = config('gamification.badges.thresholds');

        $this->assertCount(count(BadgeRuleKey::cases()), $thresholds);
        $this->assertSame(['bronze' => 100, 'silver' => 1000, 'gold' => 5000], config(BadgeRuleKey::SportDistance->thresholdsConfigPath()));
        $this->assertSame(['bronze' => 25, 'silver' => 250, 'gold' => 1000], config(BadgeRuleKey::TodoTasksCompleted->thresholdsConfigPath()));

        foreach ($thresholds as $tiers) {
            $this->assertSame(['bronze', 'silver', 'gold'], array_keys($tiers));
        }
    }

    #[Test]
    public function it_translates_every_family_name_and_description(): void
    {
        $this->app->setLocale('fr');

        foreach (BadgeRuleKey::cases() as $ruleKey) {
            $this->assertNotSame("gamification::badges.rules.{$ruleKey->value}.name", $ruleKey->label());
            $this->assertStringContainsString('42', $ruleKey->description('42'));
        }
    }
}

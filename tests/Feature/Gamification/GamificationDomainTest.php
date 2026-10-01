<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationDomainTest extends TestCase
{
    #[Test]
    public function it_labels_every_domain_in_french(): void
    {
        $this->app->setLocale('fr');

        foreach (GamificationDomain::cases() as $domain) {
            $this->assertStringNotContainsString('gamification::', $domain->label());
        }

        $this->assertSame('Sport', GamificationDomain::Sport->label());
        $this->assertSame('Santé', GamificationDomain::Health->label());
    }

    #[Test]
    public function it_labels_every_domain_in_english(): void
    {
        $this->app->setLocale('en');

        foreach (GamificationDomain::cases() as $domain) {
            $this->assertStringNotContainsString('gamification::', $domain->label());
        }

        $this->assertSame('Health', GamificationDomain::Health->label());
        $this->assertSame('Tasks', GamificationDomain::Todo->label());
    }

    #[Test]
    public function it_maps_every_domain_to_a_rendered_neon_accent(): void
    {
        foreach (GamificationDomain::cases() as $domain) {
            $this->assertContains($domain->color(), ['cyan', 'violet', 'lime']);
        }

        $this->assertSame('cyan', GamificationDomain::Sport->color());
        $this->assertSame('lime', GamificationDomain::Finance->color());
    }

    #[Test]
    public function it_exposes_an_icon_path_for_every_domain(): void
    {
        foreach (GamificationDomain::cases() as $domain) {
            $this->assertNotSame('', $domain->icon());
        }
    }

    #[Test]
    public function it_tracks_streaks_on_every_daily_domain_but_finance(): void
    {
        $this->assertNotContains(GamificationDomain::Finance, GamificationDomain::streakDomains());
        $this->assertCount(count(GamificationDomain::cases()) - 1, GamificationDomain::streakDomains());
    }

    #[Test]
    public function it_exposes_literal_accent_classes_matching_the_domain_color(): void
    {
        foreach (GamificationDomain::cases() as $domain) {
            $this->assertSame("text-{$domain->color()}", $domain->textClass());
            $this->assertSame("bg-{$domain->color()}-soft", $domain->softBackgroundClass());
        }
    }
}

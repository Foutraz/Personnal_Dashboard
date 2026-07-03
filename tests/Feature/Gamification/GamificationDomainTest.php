<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationDomainTest extends TestCase
{
    #[Test]
    public function it_labels_every_domain(): void
    {
        foreach (GamificationDomain::cases() as $domain) {
            $this->assertNotSame('', $domain->label());
        }

        $this->assertSame('Sport', GamificationDomain::Sport->label());
        $this->assertSame('Santé', GamificationDomain::Health->label());
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
}

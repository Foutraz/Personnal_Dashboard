<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeUnit;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeUnitTest extends TestCase
{
    #[Test]
    public function it_formats_every_unit_in_french_with_the_french_digit_grouping(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('1 250 km', Str::squish(BadgeUnit::Kilometers->format(1250)));
        $this->assertSame('1 250', Str::squish(BadgeUnit::Count->format(1250)));
        $this->assertSame('1 250 j', Str::squish(BadgeUnit::Days->format(1250)));
        $this->assertSame('1 250 €', Str::squish(BadgeUnit::Euros->format(1250)));
    }

    #[Test]
    public function it_formats_every_unit_in_english_with_the_english_digit_grouping(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('1,250 km', BadgeUnit::Kilometers->format(1250));
        $this->assertSame('1,250', BadgeUnit::Count->format(1250));
        $this->assertSame('1,250 d', BadgeUnit::Days->format(1250));
        $this->assertSame('1,250 €', BadgeUnit::Euros->format(1250));
    }

    #[Test]
    public function it_keeps_a_single_decimal_at_most(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('150.4 km', BadgeUnit::Kilometers->format(150.37));
        $this->assertSame('150 km', BadgeUnit::Kilometers->format(150.0));
    }
}

<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeUnit;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeUnitTest extends TestCase
{
    #[Test]
    public function it_formats_every_unit_in_french(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('12,5 km', Str::squish(ChallengeUnit::Kilometers->format(12.5)));
        $this->assertSame('1 000 m', Str::squish(ChallengeUnit::Meters->format(1000.0)));
        $this->assertSame('3,5 h', Str::squish(ChallengeUnit::Hours->format(3.5)));
        $this->assertSame('2', Str::squish(ChallengeUnit::Count->format(2.0)));
    }

    #[Test]
    public function it_formats_every_unit_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('12.5 km', ChallengeUnit::Kilometers->format(12.5));
        $this->assertSame('1,000 m', ChallengeUnit::Meters->format(1000.0));
        $this->assertSame('3.5 h', ChallengeUnit::Hours->format(3.5));
        $this->assertSame('2', ChallengeUnit::Count->format(2.0));
    }

    #[Test]
    public function it_keeps_one_decimal_for_kilometers_and_hours_only(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('150.4 km', ChallengeUnit::Kilometers->format(150.37));
        $this->assertSame('1.3 h', ChallengeUnit::Hours->format(1.26));
        $this->assertSame('1,251 m', ChallengeUnit::Meters->format(1250.6));
        $this->assertSame('13', ChallengeUnit::Count->format(12.6));
    }

    #[Test]
    public function it_drops_the_decimal_of_a_whole_measure(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('28 km', ChallengeUnit::Kilometers->format(28.0));
        $this->assertSame('4 h', ChallengeUnit::Hours->format(4.0));
    }

    #[Test]
    public function it_formats_a_bare_number_with_the_locale_digit_grouping(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('1,250', ChallengeUnit::Meters->formatNumber(1250.0));
        $this->assertSame('12.5', ChallengeUnit::Kilometers->formatNumber(12.5));

        $this->app->setLocale('fr');
        $this->assertSame('1 250', Str::squish(ChallengeUnit::Meters->formatNumber(1250.0)));
        $this->assertSame('3,5', ChallengeUnit::Hours->formatNumber(3.5));
    }

    #[Test]
    public function it_exposes_the_measure_placeholder_in_every_unit_translation(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach (ChallengeUnit::cases() as $unit) {
                $this->assertStringContainsString('12', __("gamification::challenges.units.{$unit->value}", ['measure' => '12']), "{$locale}.{$unit->value}");
            }
        }
    }
}

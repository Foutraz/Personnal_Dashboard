<?php

namespace Tests\Feature\Gamification;

use Carbon\CarbonImmutable;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationWeekTest extends TestCase
{
    #[Test]
    public function it_resolves_the_iso_week_with_paris_boundaries_converted_to_the_application_timezone(): void
    {
        $week = $this->weekOf('2026-10-01 10:00:00');

        $this->assertInstanceOf(GamificationWeek::class, $week);
        $this->assertSame('2026-W40', $week->key());
        $this->assertSame(2026, $week->isoYear);
        $this->assertSame(40, $week->isoWeek);
        $this->assertSame('2026-09-28', $week->startDate->toDateString());
        $this->assertSame('2026-09-27 22:00:00', $week->startsAt->toDateTimeString());
        $this->assertSame('2026-10-04 22:00:00', $week->endsAt->toDateTimeString());
        $this->assertSame('UTC', $week->startsAt->timezoneName);
        $this->assertSame('UTC', $week->endsAt->timezoneName);
    }

    #[Test]
    public function it_keeps_the_last_second_before_the_paris_monday_in_the_current_week(): void
    {
        $week = $this->weekOf('2026-10-04 21:59:59');

        $this->assertSame('2026-W40', $week->key());
    }

    #[Test]
    public function it_starts_the_next_week_at_the_paris_monday_midnight(): void
    {
        $week = $this->weekOf('2026-10-04 22:00:00');

        $this->assertSame('2026-W41', $week->key());
        $this->assertSame('2026-10-05', $week->startDate->toDateString());
        $this->assertSame('2026-10-04 22:00:00', $week->startsAt->toDateTimeString());
    }

    #[Test]
    public function it_places_a_moment_zoned_in_paris_in_the_week_of_its_local_date(): void
    {
        $week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris'));

        $this->assertSame('2026-W41', $week->key());
        $this->assertSame('2026-10-04 22:00:00', $week->startsAt->toDateTimeString());
    }

    #[Test]
    public function it_does_not_alter_the_moment_it_resolves(): void
    {
        $moment = Carbon::parse('2026-10-01 10:00:00', 'UTC');

        $this->app->make(GamificationCalendar::class)->weekOf($moment);

        $this->assertSame('UTC', $moment->timezoneName);
        $this->assertSame('2026-10-01 10:00:00', $moment->toDateTimeString());
    }

    #[Test]
    public function it_spans_one_hour_more_when_the_week_crosses_the_winter_time_change(): void
    {
        $week = $this->weekOf('2026-10-25 12:00:00');

        $this->assertSame('2026-W43', $week->key());
        $this->assertSame('2026-10-19', $week->startDate->toDateString());
        $this->assertSame('2026-10-18 22:00:00', $week->startsAt->toDateTimeString());
        $this->assertSame('2026-10-25 23:00:00', $week->endsAt->toDateTimeString());
        $this->assertSame(169.0, $week->startsAt->diffInHours($week->endsAt));
    }

    #[Test]
    public function it_resolves_the_iso_week_fifty_three(): void
    {
        $week = $this->weekOf('2027-01-03 12:00:00');

        $this->assertSame('2026-W53', $week->key());
        $this->assertSame(2026, $week->isoYear);
        $this->assertSame(53, $week->isoWeek);
        $this->assertSame('2026-12-28', $week->startDate->toDateString());
    }

    #[Test]
    public function it_goes_back_to_week_fifty_three_from_the_first_week_of_the_following_iso_year(): void
    {
        $week = $this->weekOf('2027-01-04 12:00:00');

        $this->assertSame('2027-W01', $week->key());
        $this->assertSame('2026-W53', $week->previous()->key());
    }

    #[Test]
    public function it_goes_back_several_weeks(): void
    {
        $previous = $this->weekOf('2026-10-01 10:00:00')->previous(4);

        $this->assertSame('2026-W36', $previous->key());
        $this->assertSame('2026-08-31', $previous->startDate->toDateString());
        $this->assertSame('2026-08-30 22:00:00', $previous->startsAt->toDateTimeString());
        $this->assertSame('2026-09-06 22:00:00', $previous->endsAt->toDateTimeString());
    }

    #[Test]
    public function it_goes_back_across_the_winter_time_change_keeping_the_paris_midnight(): void
    {
        $previous = $this->weekOf('2026-11-02 12:00:00')->previous(2);

        $this->assertSame('2026-W43', $previous->key());
        $this->assertSame('2026-10-18 22:00:00', $previous->startsAt->toDateTimeString());
        $this->assertSame('2026-10-25 23:00:00', $previous->endsAt->toDateTimeString());
        $this->assertSame('UTC', $previous->startsAt->timezoneName);
    }

    #[Test]
    public function it_goes_back_one_week_by_default(): void
    {
        $this->assertSame('2026-W39', $this->weekOf('2026-10-01 10:00:00')->previous()->key());
    }

    #[Test]
    public function it_exposes_the_last_second_of_the_week_as_its_last_moment(): void
    {
        $this->assertSame('2026-10-04 21:59:59', $this->weekOf('2026-10-01 10:00:00')->lastMoment()->toDateTimeString());
    }

    #[Test]
    public function it_ends_exactly_at_the_end_instant(): void
    {
        $week = $this->weekOf('2026-10-01 10:00:00');

        $this->assertFalse($week->hasEnded(Carbon::parse('2026-10-04 21:59:59', 'UTC')));
        $this->assertTrue($week->hasEnded(Carbon::parse('2026-10-04 22:00:00', 'UTC')));
    }

    #[Test]
    public function it_closes_the_grace_hours_after_the_end_of_the_week(): void
    {
        $closesAt = $this->weekOf('2026-10-01 10:00:00')->closesAt(48);

        $this->assertInstanceOf(CarbonImmutable::class, $closesAt);
        $this->assertSame('2026-10-06 22:00:00', $closesAt->toDateTimeString());
        $this->assertSame('UTC', $closesAt->timezoneName);
    }

    #[Test]
    public function it_closes_with_the_end_of_the_week_when_the_grace_is_zero(): void
    {
        $this->assertSame('2026-10-04 22:00:00', $this->weekOf('2026-10-01 10:00:00')->closesAt(0)->toDateTimeString());
    }

    #[Test]
    public function it_adds_real_hours_across_the_autumn_clock_change(): void
    {
        $this->assertSame('2026-10-27 23:00:00', $this->weekOf('2026-10-21 10:00:00')->closesAt(48)->toDateTimeString());
    }

    #[Test]
    public function it_leaves_the_end_of_the_week_untouched_when_computing_the_closing(): void
    {
        $week = $this->weekOf('2026-10-01 10:00:00');

        $week->closesAt(48);

        $this->assertSame('2026-10-04 22:00:00', $week->endsAt->toDateTimeString());
    }

    #[Test]
    public function it_compares_moments_of_any_timezone_against_the_end_instant(): void
    {
        $week = $this->weekOf('2026-10-01 10:00:00');

        $this->assertTrue($week->hasEnded(Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris')));
        $this->assertFalse($week->hasEnded(Carbon::parse('2026-10-04 23:59:59', 'Europe/Paris')));
    }

    #[Test]
    public function it_falls_back_to_the_application_timezone_boundaries_when_the_configured_timezone_is_invalid(): void
    {
        config(['gamification.timezone' => 'Mars/Olympus']);

        $week = $this->weekOf('2026-10-01 10:00:00');

        $this->assertSame('2026-W40', $week->key());
        $this->assertSame('2026-09-28 00:00:00', $week->startsAt->toDateTimeString());
        $this->assertSame('2026-10-05 00:00:00', $week->endsAt->toDateTimeString());
    }

    #[Test]
    public function it_resolves_the_current_week_from_the_present_instant(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));

        $week = $this->app->make(GamificationCalendar::class)->currentWeek();

        $this->assertSame('2026-W40', $week->key());
        $this->assertSame('2026-09-27 22:00:00', $week->startsAt->toDateTimeString());
    }

    private function weekOf(string $utcMoment): GamificationWeek
    {
        return $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse($utcMoment, 'UTC'));
    }
}

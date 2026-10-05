<?php

namespace Tests\Feature\Application;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Application\Exceptions\InvalidDisplayTimezoneException;
use Technical\Application\Time\DisplayTimezone;
use Tests\TestCase;

class DisplayTimezoneTest extends TestCase
{
    private function displayTimezone(): DisplayTimezone
    {
        return $this->app->make(DisplayTimezone::class);
    }

    #[Test]
    public function it_defaults_the_display_timezone_to_europe_paris(): void
    {
        $this->assertSame('Europe/Paris', config('app.display_timezone'));
        $this->assertSame('Europe/Paris', $this->displayTimezone()->name());
    }

    #[Test]
    public function it_keeps_storing_instants_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
    }

    #[Test]
    #[DataProvider('invalidConfiguredTimezones')]
    public function it_rejects_a_display_timezone_that_is_not_a_timezone_identifier(mixed $configured, string $received): void
    {
        Config::set('app.display_timezone', $configured);

        $this->expectException(InvalidDisplayTimezoneException::class);
        $this->expectExceptionMessageMatches('/APP_DISPLAY_TIMEZONE.*app\.display_timezone.*'.preg_quote($received, '/').'/');

        $this->displayTimezone()->name();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfiguredTimezones(): array
    {
        return [
            'unknown identifier' => ['Mars/Olympus', '"Mars/Olympus"'],
            'empty string' => ['', '""'],
            'missing value' => [null, 'null'],
            'array value' => [['Europe/Paris'], 'array'],
        ];
    }

    #[Test]
    #[DataProvider('localInputs')]
    public function it_converts_a_local_input_to_the_application_instant(string $displayTimezone, string $localInput, string $expectedUtc): void
    {
        Config::set('app.display_timezone', $displayTimezone);

        $instant = $this->displayTimezone()->toApplicationTime($localInput);

        $this->assertSame($expectedUtc, $instant->toDateTimeString());
        $this->assertSame('UTC', $instant->getTimezone()->getName());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function localInputs(): array
    {
        return [
            'Paris summer time' => ['Europe/Paris', '2026-10-04T23:30', '2026-10-04 21:30:00'],
            'Paris winter time' => ['Europe/Paris', '2026-12-06T23:30', '2026-12-06 22:30:00'],
            'New York behind UTC' => ['America/New_York', '2026-10-04T23:30', '2026-10-05 03:30:00'],
        ];
    }

    #[Test]
    public function it_converts_an_instant_to_the_display_timezone(): void
    {
        $instant = CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC');

        $displayTime = $this->displayTimezone()->toDisplayTime($instant);

        $this->assertSame('2026-10-04 23:30', $displayTime->format('Y-m-d H:i'));
        $this->assertSame('Europe/Paris', $displayTime->getTimezone()->getName());
    }

    #[Test]
    public function it_leaves_a_mutable_instant_untouched_when_converting_it(): void
    {
        $instant = Carbon::parse('2026-10-04 21:30:00', 'UTC');

        $this->displayTimezone()->toDisplayTime($instant);

        $this->assertSame('UTC', $instant->getTimezone()->getName());
        $this->assertSame('2026-10-04 21:30:00', $instant->toDateTimeString());
    }

    #[Test]
    public function it_formats_an_instant_as_a_datetime_local_input_value(): void
    {
        $instant = CarbonImmutable::parse('2026-12-06 22:30:00', 'UTC');

        $this->assertSame('2026-12-06T23:30', $this->displayTimezone()->inputValue($instant));
    }

    #[Test]
    public function it_round_trips_an_input_value_through_the_application_instant(): void
    {
        $instant = $this->displayTimezone()->toApplicationTime('2026-10-04T23:30');

        $this->assertSame('2026-10-04T23:30', $this->displayTimezone()->inputValue($instant));
    }

    #[Test]
    #[DataProvider('minutesAroundNow')]
    public function it_places_a_local_input_on_the_right_side_of_now_in_both_directions_from_utc(string $displayTimezone, int $minutesFromNow, bool $expectedInTheFuture): void
    {
        Config::set('app.display_timezone', $displayTimezone);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'));
        $localInput = CarbonImmutable::now($displayTimezone)->addMinutes($minutesFromNow)->format(DisplayTimezone::INPUT_FORMAT);

        $instant = $this->displayTimezone()->toApplicationTime($localInput);

        $this->assertSame($expectedInTheFuture, $instant->isFuture());
        $this->assertSame(! $expectedInTheFuture, $instant->isPast());
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function minutesAroundNow(): array
    {
        return [
            'ahead of UTC, one minute ago' => ['Europe/Paris', -1, false],
            'ahead of UTC, one minute from now' => ['Europe/Paris', 1, true],
            'behind UTC, one minute ago' => ['America/New_York', -1, false],
            'behind UTC, one minute from now' => ['America/New_York', 1, true],
        ];
    }
}

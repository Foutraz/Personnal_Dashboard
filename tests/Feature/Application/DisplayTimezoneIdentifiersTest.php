<?php

namespace Tests\Feature\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Application\Exceptions\InvalidDisplayTimezoneException;
use Technical\Application\Time\DisplayTimezone;
use Tests\TestCase;

class DisplayTimezoneIdentifiersTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validIdentifiers(): array
    {
        return [
            'region city' => ['Europe/Paris'],
            'utc' => ['UTC'],
            'etc utc' => ['Etc/UTC'],
            'gmt' => ['GMT'],
        ];
    }

    #[Test]
    #[DataProvider('validIdentifiers')]
    public function it_accepts_a_valid_timezone_identifier(string $identifier): void
    {
        Config::set('app.display_timezone', $identifier);

        $this->assertSame($identifier, app(DisplayTimezone::class)->name());
    }

    #[Test]
    public function it_converts_an_instant_into_a_utc_alias_timezone(): void
    {
        Config::set('app.display_timezone', 'Etc/UTC');

        $displayTime = app(DisplayTimezone::class)->toDisplayTime(CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'));

        $this->assertSame('2026-10-04 21:30', $displayTime->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_shares_one_instance_for_the_whole_request(): void
    {
        $this->assertSame(app(DisplayTimezone::class), app(DisplayTimezone::class));
    }

    #[Test]
    public function it_follows_the_configuration_after_a_first_resolution(): void
    {
        $displayTimezone = app(DisplayTimezone::class);
        $this->assertSame('Europe/Paris', $displayTimezone->name());

        Config::set('app.display_timezone', 'America/New_York');

        $this->assertSame('America/New_York', $displayTimezone->name());
    }

    #[Test]
    public function it_still_rejects_an_invalid_value_configured_after_a_valid_one_was_resolved(): void
    {
        $displayTimezone = app(DisplayTimezone::class);
        $displayTimezone->name();
        Config::set('app.display_timezone', 'Mars/Olympus');

        $this->expectException(InvalidDisplayTimezoneException::class);

        $displayTimezone->name();
    }
}

<?php

namespace Tests\Feature\Application;

use Carbon\Carbon as MutableCarbon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Technical\Application\Exceptions\InvalidDisplayTimezoneException;
use Tests\TestCase;

class InDisplayTimezoneMacroTest extends TestCase
{
    private string $originalCarbonLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCarbonLocale = Carbon::getLocale();
    }

    protected function tearDown(): void
    {
        Carbon::setLocale($this->originalCarbonLocale);

        parent::tearDown();
    }

    #[Test]
    public function it_shifts_an_application_carbon_to_the_display_timezone(): void
    {
        $displayTime = Carbon::parse('2026-10-04 21:30:00', 'UTC')->inDisplayTimezone();

        $this->assertSame('2026-10-04 23:30', $displayTime->format('Y-m-d H:i'));
        $this->assertSame('Europe/Paris', $displayTime->getTimezone()->getName());
    }

    #[Test]
    public function it_shifts_a_plain_carbon_to_the_display_timezone(): void
    {
        $displayTime = MutableCarbon::parse('2026-12-06 23:30:00', 'UTC')->inDisplayTimezone();

        $this->assertSame('2026-12-07 00:30', $displayTime->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_leaves_the_original_instant_in_its_timezone(): void
    {
        $instant = Carbon::parse('2026-10-04 21:30:00', 'UTC');

        $instant->inDisplayTimezone();

        $this->assertSame('UTC', $instant->getTimezone()->getName());
        $this->assertSame('2026-10-04 21:30:00', $instant->toDateTimeString());
    }

    #[Test]
    public function it_follows_the_configured_display_timezone(): void
    {
        Config::set('app.display_timezone', 'America/New_York');

        $displayTime = Carbon::parse('2026-10-05 02:30:00', 'UTC')->inDisplayTimezone();

        $this->assertSame('2026-10-04 22:30', $displayTime->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_fails_loudly_when_the_display_timezone_is_misconfigured(): void
    {
        Config::set('app.display_timezone', 'Mars/Olympus');

        $this->expectException(InvalidDisplayTimezoneException::class);

        Carbon::parse('2026-10-04 21:30:00', 'UTC')->inDisplayTimezone();
    }

    #[Test]
    public function it_formats_the_weekday_in_the_application_locale(): void
    {
        $this->app->setLocale('fr');

        $weekday = Carbon::parse('2026-10-04 21:30:00', 'UTC')->inDisplayTimezone()->translatedFormat('l');

        $this->assertSame('dimanche', $weekday);
    }
}

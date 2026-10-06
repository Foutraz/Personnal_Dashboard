<?php

namespace Tests\Unit\Osdd;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Rules\IsoDatetime;
use Tests\TestCase;

class IsoDatetimeTest extends TestCase
{
    private function passes(mixed $input): bool
    {
        return Validator::make(['moment' => $input], ['moment' => [new IsoDatetime]])->passes();
    }

    public static function acceptedInputs(): array
    {
        return [
            'date only' => ['2026-10-01'],
            'minutes with a space' => ['2026-10-01 08:00'],
            'minutes with a t' => ['2026-10-01T08:00'],
            'seconds with a space' => ['2026-10-01 08:00:00'],
            'seconds with a t' => ['2026-10-01T08:00:00'],
            'fractional seconds' => ['2026-10-01 08:00:00.123456'],
            'zulu marker' => ['2026-10-01T10:00:00Z'],
            'positive offset with a colon' => ['2026-10-01T23:00:00+14:00'],
            'negative offset without a colon' => ['2026-10-01T05:00:00-0500'],
            'offset on minutes' => ['2026-10-01T05:00-05:00'],
            'lowercase markers' => ['2026-10-01t10:00:00z'],
            'the epoch floor with an offset' => ['1970-01-01T00:00:00-14:00'],
        ];
    }

    public static function rejectedInputs(): array
    {
        return [
            'digits read as a day of the year' => ['01800041970'],
            'digits read as a day month year' => ['001812081982'],
            'digits read as a date and minutes' => ['202609301010'],
            'compact date' => ['20260930'],
            'unix timestamp string' => ['1790845200'],
            'unix timestamp marker' => ['@1790845200'],
            'relative word' => ['yesterday'],
            'now' => ['now'],
            'relative offset' => ['+1 week'],
            'zone identifier' => ['2026-10-01 23:00:00 Pacific/Kiritimati'],
            'zone abbreviation' => ['2026-10-01 08:00:00 EST'],
            'slashes' => ['10/01/2026'],
            'two digit year' => ['26-10-01'],
            'time only' => ['08:00:00'],
            'offset without a time' => ['2026-10-01+14:00'],
            'trailing newline' => ["2026-10-01\n"],
            'leading space' => [' 2026-10-01'],
            'trailing words' => ['2026-10-01 08:00:00 next friday'],
            'integer' => [20260930],
            'float' => [1790845200.0],
            'boolean' => [true],
            'array' => [['2026-10-01']],
        ];
    }

    #[Test]
    #[DataProvider('acceptedInputs')]
    public function it_accepts_an_iso_shaped_moment(string $input): void
    {
        $this->assertTrue($this->passes($input));
    }

    #[Test]
    #[DataProvider('rejectedInputs')]
    public function it_rejects_anything_that_is_not_an_iso_shaped_string(mixed $input): void
    {
        $this->assertFalse($this->passes($input));
    }
}

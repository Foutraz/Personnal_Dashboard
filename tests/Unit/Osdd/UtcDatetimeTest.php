<?php

namespace Tests\Unit\Osdd;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Technical\Osdd\Casts\UtcDatetime;
use Technical\Osdd\Exceptions\UnparsableDatetimeException;
use Tests\TestCase;

class UtcDatetimeTest extends TestCase
{
    private function model(): Model
    {
        return new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return ['moment' => UtcDatetime::class];
            }
        };
    }

    private function storedFor(mixed $moment): ?string
    {
        $model = $this->model();
        $model->moment = $moment;

        return $model->getAttributes()['moment'];
    }

    public static function storedMoments(): array
    {
        return [
            'carbon at +14:00' => [Carbon::parse('2026-10-01T23:00:00+14:00'), '2026-10-01 09:00:00'],
            'carbon at -14:00' => [Carbon::parse('1970-01-01T00:00:00-14:00'), '1970-01-01 14:00:00'],
            'carbon in a named zone' => [Carbon::parse('2026-10-01 23:00:00', 'Pacific/Kiritimati'), '2026-10-01 09:00:00'],
            'carbon in the application zone' => [Carbon::parse('2026-10-01 09:00:00', 'UTC'), '2026-10-01 09:00:00'],
            'immutable carbon in a named zone' => [CarbonImmutable::parse('2026-10-01 05:00:00', 'America/New_York'), '2026-10-01 09:00:00'],
            'native datetime with an offset' => [new DateTime('2026-10-01T05:00:00-05:00'), '2026-10-01 10:00:00'],
            'native immutable datetime in a named zone' => [new DateTimeImmutable('2026-10-01 23:00:00', new DateTimeZone('Pacific/Kiritimati')), '2026-10-01 09:00:00'],
            'string with a positive offset' => ['2026-10-01T23:00:00+14:00', '2026-10-01 09:00:00'],
            'string with a negative offset' => ['2026-10-01T05:00:00-05:00', '2026-10-01 10:00:00'],
            'string with the zulu marker' => ['2026-10-01T10:00:00Z', '2026-10-01 10:00:00'],
            'string with a zone identifier' => ['2026-10-01 23:00:00 Pacific/Kiritimati', '2026-10-01 09:00:00'],
            'string without an offset' => ['2026-10-01 08:30:00', '2026-10-01 08:30:00'],
            'date only string' => ['2026-10-01', '2026-10-01 00:00:00'],
            'unix timestamp' => [1_790_848_800, '2026-10-01 10:00:00'],
            'float unix timestamp' => [1_790_848_800.0, '2026-10-01 10:00:00'],
            'compact date string is a date, not a timestamp' => ['20260930', '2026-09-30 00:00:00'],
            'digit string is a date, not a timestamp' => ['01800041970', '1970-01-04 00:00:00'],
            'compact date and minutes string' => ['202609301010', '2026-09-30 10:10:00'],
            'fractional seconds are truncated' => ['2026-10-01T23:00:00.987+14:00', '2026-10-01 09:00:00'],
            'null' => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('storedMoments')]
    public function it_stores_every_moment_in_the_application_timezone(mixed $moment, ?string $stored): void
    {
        $this->assertSame($stored, $this->storedFor($moment));
    }

    #[Test]
    public function it_does_not_mutate_the_carbon_it_is_given(): void
    {
        $moment = Carbon::parse('2026-10-01T23:00:00+14:00');

        $this->storedFor($moment);

        $this->assertSame('+14:00', $moment->getTimezone()->getName());
        $this->assertSame('2026-10-01 23:00:00', $moment->toDateTimeString());
    }

    #[Test]
    public function it_reads_a_stored_moment_as_a_carbon_in_the_application_timezone(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => '2026-10-01 09:00:00']);

        $this->assertInstanceOf(Carbon::class, $model->moment);
        $this->assertSame('UTC', $model->moment->getTimezone()->getName());
        $this->assertSame('2026-10-01 09:00:00', $model->moment->toDateTimeString());
    }

    #[Test]
    public function it_reads_a_null_as_null(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => null]);

        $this->assertNull($model->moment);
    }

    #[Test]
    public function it_reads_back_in_the_application_timezone_what_was_set_in_another_one(): void
    {
        $model = $this->model();
        $model->moment = Carbon::parse('2026-10-01T23:00:00+14:00');

        $this->assertSame('UTC', $model->moment->getTimezone()->getName());
        $this->assertSame('2026-10-01 09:00:00', $model->moment->toDateTimeString());
    }

    #[Test]
    public function it_serialises_the_moment_as_an_utc_iso_string(): void
    {
        $model = $this->model();
        $model->moment = '2026-10-01T23:00:00+14:00';

        $this->assertSame('2026-10-01T09:00:00.000000Z', $model->toArray()['moment']);
    }

    #[Test]
    public function it_does_not_flag_the_same_instant_written_in_another_zone_as_dirty(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => '2026-10-01 09:00:00'], true);

        $model->moment = '2026-10-01T23:00:00+14:00';

        $this->assertFalse($model->isDirty('moment'));
    }

    #[Test]
    public function it_does_not_flag_the_same_instant_stored_in_another_textual_format_as_dirty(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => '2026-10-01T09:00:00+00:00'], true);

        $model->moment = '2026-10-01 09:00:00';

        $this->assertFalse($model->isDirty('moment'));
    }

    #[Test]
    public function it_flags_a_different_instant_as_dirty(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => '2026-10-01 09:00:00'], true);

        $model->moment = '2026-10-01T23:00:01+14:00';

        $this->assertTrue($model->isDirty('moment'));
    }

    #[Test]
    public function it_flags_a_moment_set_over_a_null_as_dirty(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['moment' => null], true);

        $model->moment = '2026-10-01 09:00:00';

        $this->assertTrue($model->isDirty('moment'));
    }

    #[Test]
    public function it_does_not_read_a_digit_string_as_a_timestamp(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->storedFor('1790845200');
    }

    public static function unparsableMoments(): array
    {
        return [
            'array' => [['2026-10-01']],
            'boolean' => [true],
            'object' => [new stdClass],
        ];
    }

    #[Test]
    #[DataProvider('unparsableMoments')]
    public function it_refuses_a_value_that_is_not_a_moment(mixed $moment): void
    {
        $this->expectException(UnparsableDatetimeException::class);

        $this->storedFor($moment);
    }
}

<?php

namespace Tests\Unit\Moto;

use DateTimeImmutable;
use Foutraz\Weather\Dto\Forecast;
use Foutraz\Weather\Dto\ForecastEntry;
use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\MotoFriendlyScore;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavorableSlotFinderTest extends TestCase
{
    private function finder(): FavorableSlotFinder
    {
        return new FavorableSlotFinder(new MotoFriendlyScore);
    }

    /**
     * @param  array{pop?: float, rain3h?: float|null, windSpeed?: float, temp?: float, visibility?: int|null}  $overrides
     */
    private function entry(string $at, array $overrides = []): ForecastEntry
    {
        return new ForecastEntry(
            new DateTimeImmutable($at),
            $overrides['temp'] ?? 21.0,
            $overrides['temp'] ?? 21.0,
            $overrides['windSpeed'] ?? 2.0,
            null,
            $overrides['pop'] ?? 0.0,
            $overrides['rain3h'] ?? null,
            10,
            $overrides['visibility'] ?? 10000,
            'Clear',
            'clear sky',
        );
    }

    #[Test]
    public function it_finds_a_contiguous_good_window(): void
    {
        $forecast = new Forecast(48.0, 2.0, [
            $this->entry('2026-06-26T09:00:00Z'),
            $this->entry('2026-06-26T12:00:00Z'),
            $this->entry('2026-06-26T15:00:00Z'),
            $this->entry('2026-06-26T18:00:00Z', ['pop' => 0.9, 'rain3h' => 9.0]),
        ]);

        $slots = $this->finder()->find($forecast);

        $this->assertCount(1, $slots);
        $this->assertGreaterThanOrEqual(65, $slots[0]->score);
        $this->assertEquals(new DateTimeImmutable('2026-06-26T09:00:00Z'), $slots[0]->startsAt);
        $this->assertEquals(new DateTimeImmutable('2026-06-26T18:00:00Z'), $slots[0]->endsAt);
    }

    #[Test]
    public function it_returns_no_slot_when_every_entry_is_bad(): void
    {
        $forecast = new Forecast(48.0, 2.0, [
            $this->entry('2026-06-26T09:00:00Z', ['pop' => 1.0, 'rain3h' => 12.0, 'windSpeed' => 20.0]),
            $this->entry('2026-06-26T12:00:00Z', ['pop' => 1.0, 'rain3h' => 12.0, 'windSpeed' => 20.0]),
        ]);

        $this->assertSame([], $this->finder()->find($forecast));
    }

    #[Test]
    public function it_caps_the_number_of_returned_slots(): void
    {
        $entries = [];

        for ($hour = 0; $hour < 40; $hour += 3) {
            $bad = ($hour / 3) % 2 === 1;
            $entries[] = $this->entry(
                sprintf('2026-06-26T%02d:00:00Z', $hour % 24),
                $bad ? ['pop' => 1.0, 'rain3h' => 12.0] : [],
            );
        }

        $forecast = new Forecast(48.0, 2.0, $entries);

        $this->assertLessThanOrEqual((int) config('moto.slots.maximum'), count($this->finder()->find($forecast)));
    }
}

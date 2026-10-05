<?php

namespace Functional\Gamification\Services\Dto;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;

/**
 * An ISO game week whose half-open bounds startsAt and endsAt are in the application timezone, because querying with the local startDate would shift the week by the Paris offset.
 */
final readonly class GamificationWeek
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $isoYear,
        public int $isoWeek,
    ) {}

    public static function startingOn(CarbonImmutable $localMonday, CarbonTimeZone|string $applicationTimezone): self
    {
        return new self(
            startDate: $localMonday,
            startsAt: $localMonday->setTimezone($applicationTimezone),
            endsAt: $localMonday->addWeek()->setTimezone($applicationTimezone),
            isoYear: $localMonday->isoWeekYear,
            isoWeek: $localMonday->isoWeek,
        );
    }

    public function key(): string
    {
        return sprintf('%d-W%02d', $this->isoYear, $this->isoWeek);
    }

    public function previous(int $weeks = 1): self
    {
        return self::startingOn($this->startDate->subWeeks($weeks), $this->startsAt->getTimezone());
    }

    public function lastMoment(): CarbonImmutable
    {
        return $this->endsAt->subSecond();
    }

    public function hasEnded(CarbonInterface $moment): bool
    {
        return $moment->greaterThanOrEqualTo($this->endsAt);
    }

    public function isPastGrace(CarbonInterface $moment, int $graceHours): bool
    {
        return $moment->greaterThanOrEqualTo($this->endsAt->addHours($graceHours));
    }
}

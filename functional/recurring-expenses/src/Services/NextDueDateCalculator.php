<?php

namespace Functional\RecurringExpenses\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;

class NextDueDateCalculator
{
    /**
     * Compute the first due date from the frequency, optional day-of-month and start date.
     */
    public function firstDueDate(ExpenseFrequency $frequency, ?CarbonInterface $startsAt = null, ?int $dueDay = null, ?CarbonInterface $now = null): CarbonImmutable
    {
        $reference = CarbonImmutable::instance($startsAt ?? $now ?? CarbonImmutable::now());

        if ($frequency === ExpenseFrequency::Monthly && $dueDay !== null) {
            return $this->applyDueDay($reference, $dueDay);
        }

        return $reference;
    }

    /**
     * Advance a due date forward until it is strictly in the future relative to the given moment.
     */
    public function advance(ExpenseFrequency $frequency, CarbonInterface $currentDueAt, ?int $dueDay = null, ?CarbonInterface $now = null): CarbonImmutable
    {
        $reference = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $dueAt = CarbonImmutable::instance($currentDueAt);

        while ($dueAt->lessThanOrEqualTo($reference)) {
            $dueAt = CarbonImmutable::instance($frequency->addToDate($dueAt));

            if ($frequency === ExpenseFrequency::Monthly && $dueDay !== null) {
                $dueAt = $this->applyDueDay($dueAt, $dueDay);
            }
        }

        return $dueAt;
    }

    /**
     * Clamp the requested day-of-month against the number of days in the date's month.
     */
    private function applyDueDay(CarbonImmutable $date, int $dueDay): CarbonImmutable
    {
        $day = min($dueDay, $date->daysInMonth);

        return $date->setDay($day);
    }
}

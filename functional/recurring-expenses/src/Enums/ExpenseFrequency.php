<?php

namespace Functional\RecurringExpenses\Enums;

use Carbon\CarbonInterface;

enum ExpenseFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Get the human-readable label of the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Hebdomadaire',
            self::Monthly => 'Mensuel',
            self::Quarterly => 'Trimestriel',
            self::Yearly => 'Annuel',
        };
    }

    /**
     * Advance the given date by one occurrence of the frequency.
     */
    public function addToDate(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Weekly => $date->copy()->addWeek(),
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3),
            self::Yearly => $date->copy()->addYearNoOverflow(),
        };
    }

    /**
     * Get the number of occurrences of the frequency within a year.
     */
    public function occurrencesPerYear(): float
    {
        return match ($this) {
            self::Weekly => 52.0,
            self::Monthly => 12.0,
            self::Quarterly => 4.0,
            self::Yearly => 1.0,
        };
    }
}

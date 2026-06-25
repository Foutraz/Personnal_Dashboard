<?php

namespace Functional\Finance\Enums;

enum DcaFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    /**
     * Get the human-readable label of the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Hebdomadaire',
            self::Monthly => 'Mensuel',
            self::Quarterly => 'Trimestriel',
        };
    }

    /**
     * Get the number of contributions occurring in a single year.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Weekly => 52,
            self::Monthly => 12,
            self::Quarterly => 4,
        };
    }
}

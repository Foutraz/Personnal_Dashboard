<?php

namespace Functional\Sport\Enums;

enum EvolutionPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Get the human-readable label of the period.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semaine',
            self::Monthly => 'Mois',
            self::Yearly => 'Année',
        };
    }

    /**
     * Get the date format used to bucket activities for this period.
     */
    public function format(): string
    {
        return match ($this) {
            self::Weekly => 'o-\WW',
            self::Monthly => 'Y-m',
            self::Yearly => 'Y',
        };
    }
}

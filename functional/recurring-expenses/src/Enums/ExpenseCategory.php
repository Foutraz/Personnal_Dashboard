<?php

namespace Functional\RecurringExpenses\Enums;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Insurance = 'insurance';
    case Subscription = 'subscription';
    case Credit = 'credit';
    case Other = 'other';

    /**
     * Get the human-readable label of the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Loyer',
            self::Insurance => 'Assurance',
            self::Subscription => 'Abonnement',
            self::Credit => 'Crédit',
            self::Other => 'Autre',
        };
    }

    /**
     * Get the neon accent color associated with the category.
     */
    public function color(): string
    {
        return match ($this) {
            self::Rent => 'violet',
            self::Insurance => 'cyan',
            self::Subscription => 'lime',
            self::Credit => 'violet',
            self::Other => 'cyan',
        };
    }
}

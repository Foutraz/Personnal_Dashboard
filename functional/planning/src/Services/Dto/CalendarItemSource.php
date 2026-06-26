<?php

namespace Functional\Planning\Services\Dto;

enum CalendarItemSource: string
{
    case Google = 'google';
    case Outlook = 'outlook';
    case Expense = 'expense';

    /**
     * Get the human-readable label of the source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Calendar',
            self::Outlook => 'Outlook',
            self::Expense => 'Échéances',
        };
    }

    /**
     * Get the accent color associated with the source.
     */
    public function color(): string
    {
        return match ($this) {
            self::Google => 'cyan',
            self::Outlook => 'violet',
            self::Expense => 'lime',
        };
    }
}

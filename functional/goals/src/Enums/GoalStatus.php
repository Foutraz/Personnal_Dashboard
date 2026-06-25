<?php

namespace Functional\Goals\Enums;

enum GoalStatus: string
{
    case Active = 'active';
    case Achieved = 'achieved';
    case Archived = 'archived';

    /**
     * Get the human-readable label of the goal status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'En cours',
            self::Achieved => 'Atteint',
            self::Archived => 'Archivé',
        };
    }

    /**
     * Get the neon accent color associated with the goal status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'cyan',
            self::Achieved => 'lime',
            self::Archived => 'violet',
        };
    }
}

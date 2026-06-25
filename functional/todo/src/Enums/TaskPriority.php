<?php

namespace Functional\Todo\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Get the human-readable label of the priority.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Basse',
            self::Medium => 'Moyenne',
            self::High => 'Haute',
            self::Urgent => 'Urgente',
        };
    }

    /**
     * Get the neon accent color associated with the priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'lime',
            self::Medium => 'cyan',
            self::High => 'violet',
            self::Urgent => 'violet',
        };
    }

    /**
     * Get the sortable weight of the priority where higher means more important.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }
}

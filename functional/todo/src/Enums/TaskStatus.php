<?php

namespace Functional\Todo\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À faire',
            self::InProgress => 'En cours',
            self::Done => 'Terminée',
        };
    }

    /**
     * Get the neon accent color associated with the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'faint',
            self::InProgress => 'cyan',
            self::Done => 'lime',
        };
    }
}

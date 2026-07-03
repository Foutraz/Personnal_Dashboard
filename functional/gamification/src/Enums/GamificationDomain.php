<?php

namespace Functional\Gamification\Enums;

enum GamificationDomain: string
{
    case Sport = 'sport';
    case Health = 'health';
    case Finance = 'finance';
    case Moto = 'moto';
    case Todo = 'todo';
    case Exploration = 'exploration';

    /**
     * Get the human-readable label of the domain.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sport => 'Sport',
            self::Health => 'Santé',
            self::Finance => 'Finance',
            self::Moto => 'Moto',
            self::Todo => 'Tâches',
            self::Exploration => 'Exploration',
        };
    }

    /**
     * Get the neon accent color matching the domain's dashboard contribution.
     */
    public function color(): string
    {
        return match ($this) {
            self::Sport => 'cyan',
            self::Health => 'violet',
            self::Finance => 'lime',
            self::Moto => 'violet',
            self::Todo => 'lime',
            self::Exploration => 'violet',
        };
    }

    /**
     * Get the SVG icon path matching the domain's dashboard contribution.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Sport => 'M4 7h3l2-3h6l2 3h3M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7M9 13a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z',
            self::Health => 'M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z',
            self::Finance => 'M3 17l5-5 4 4 8-8M21 8v5h-5',
            self::Moto => 'M3 15a4 4 0 0 0 4 4h9a4 4 0 0 0 0-8 6 6 0 0 0-11.7-1.8A4 4 0 0 0 3 15Z',
            self::Todo => 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
            self::Exploration => 'M9 6 3 4v14l6 2 6-2 6 2V6l-6-2-6 2Zm0 0v14m6-12v14',
        };
    }
}

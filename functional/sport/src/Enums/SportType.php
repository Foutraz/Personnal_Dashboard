<?php

namespace Functional\Sport\Enums;

enum SportType: string
{
    case Run = 'Run';
    case Ride = 'Ride';
    case Swim = 'Swim';
    case Hike = 'Hike';
    case Walk = 'Walk';
    case VirtualRide = 'VirtualRide';
    case Workout = 'Workout';
    case Other = 'Other';

    /**
     * Resolve a sport type from a raw Strava value, defaulting to other.
     */
    public static function fromStrava(string $value): self
    {
        return self::tryFrom($value) ?? self::Other;
    }

    /**
     * Get the human-readable label of the sport type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Run => 'Course',
            self::Ride => 'Vélo',
            self::Swim => 'Natation',
            self::Hike => 'Randonnée',
            self::Walk => 'Marche',
            self::VirtualRide => 'Vélo virtuel',
            self::Workout => 'Entraînement',
            self::Other => 'Autre',
        };
    }

    /**
     * Get the canonical distance unit of the sport type.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Swim => 'm',
            default => 'km',
        };
    }
}

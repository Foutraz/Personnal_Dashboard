<?php

namespace Functional\Health\Enums;

enum MeasurementType: string
{
    case Weight = 'weight';
    case FatRatio = 'fat_ratio';
    case HeartRate = 'heart_rate';
    case Other = 'other';

    /**
     * Resolve a measurement type from a Withings type integer, defaulting to other.
     */
    public static function fromWithings(int $type): self
    {
        return match ($type) {
            1 => self::Weight,
            6 => self::FatRatio,
            11 => self::HeartRate,
            default => self::Other,
        };
    }

    /**
     * Get the human-readable label of the measurement type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weight => 'Poids',
            self::FatRatio => 'Taux de graisse',
            self::HeartRate => 'Fréquence cardiaque',
            self::Other => 'Autre',
        };
    }
}

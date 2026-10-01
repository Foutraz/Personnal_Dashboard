<?php

namespace Functional\Gamification\Enums;

use Functional\Gamification\Exceptions\InvalidBadgeTierXpException;

enum BadgeTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    /**
     * Get the human-readable label of the tier.
     */
    public function label(): string
    {
        return __("gamification::badges.tiers.{$this->value}");
    }

    public function rank(): int
    {
        return match ($this) {
            self::Bronze => 1,
            self::Silver => 2,
            self::Gold => 3,
        };
    }

    /**
     * Get the XP granted when a badge of this tier is awarded.
     *
     * @throws InvalidBadgeTierXpException
     */
    public function xpReward(): int
    {
        $xpReward = config("gamification.badges.tier_xp.{$this->value}");

        if (! is_int($xpReward) || $xpReward < 1) {
            throw new InvalidBadgeTierXpException($this, $xpReward);
        }

        return $xpReward;
    }

    /**
     * Get the hex color of the metal matching the tier.
     */
    public function accent(): string
    {
        return match ($this) {
            self::Bronze => '#cd7f32',
            self::Silver => '#c0c0c0',
            self::Gold => '#ffd700',
        };
    }
}

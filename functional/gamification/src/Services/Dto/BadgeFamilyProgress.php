<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;

final readonly class BadgeFamilyProgress
{
    /**
     * @param  array<int, BadgeMedal>  $medals
     */
    public function __construct(
        public BadgeRuleKey $ruleKey,
        public string $name,
        public GamificationDomain $domain,
        public BadgeUnit $unit,
        public array $medals,
        public float $currentValue,
        public ?float $nextThreshold,
        public float $percentage,
    ) {}

    /**
     * Determine whether every tier of the family is earned.
     */
    public function isComplete(): bool
    {
        return $this->nextThreshold === null;
    }

    /**
     * Determine whether the live measure already reaches the next tier whose award the nightly pass has not granted yet.
     */
    public function isAwaitingUnlock(): bool
    {
        return $this->nextThreshold !== null && $this->currentValue >= $this->nextThreshold;
    }

    /**
     * Count the tiers of the family the user has earned.
     */
    public function earnedCount(): int
    {
        return count(array_filter($this->medals, fn (BadgeMedal $medal): bool => $medal->earned));
    }

    /**
     * Count the tiers of the family.
     */
    public function medalCount(): int
    {
        return count($this->medals);
    }

    /**
     * Get the current measure in the unit of the family.
     */
    public function currentLabel(): string
    {
        return $this->unit->format($this->currentValue);
    }

    /**
     * Get the next threshold in the unit of the family, or null once the family is complete.
     */
    public function nextLabel(): ?string
    {
        return $this->nextThreshold === null ? null : $this->unit->format($this->nextThreshold);
    }

    /**
     * Get the progress towards the next tier as a whole percentage.
     */
    public function roundedPercentage(): int
    {
        return (int) round($this->percentage);
    }
}

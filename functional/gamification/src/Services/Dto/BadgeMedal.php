<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Models\Badge;

final readonly class BadgeMedal
{
    public function __construct(
        public BadgeTier $tier,
        public string $description,
        public string $thresholdLabel,
        public bool $earned,
    ) {}

    /**
     * Build the medal of a catalogue badge with its threshold expressed in the unit of its family.
     */
    public static function fromBadge(Badge $badge, BadgeUnit $unit, bool $earned): self
    {
        return new self(
            tier: $badge->tier,
            description: $badge->description(),
            thresholdLabel: $unit->format((float) $badge->threshold),
            earned: $earned,
        );
    }

    /**
     * Get the text alternative announcing the tier and whether the medal is earned or locked.
     */
    public function accessibleLabel(): string
    {
        $state = $this->earned ? __('gamification::badges.showcase.earned') : __('gamification::badges.showcase.locked');

        return __('gamification::badges.showcase.medal', ['tier' => $this->tier->label(), 'state' => $state]);
    }

    /**
     * Get the Tailwind classes of the medal frame, lit with the tier accent once earned.
     */
    public function frameClass(): string
    {
        return $this->earned
            ? 'border-(color:--medal) bg-surface-2 text-(color:--medal) shadow-[0_0_14px_var(--medal)]'
            : 'border-hairline bg-surface text-faint opacity-60';
    }
}

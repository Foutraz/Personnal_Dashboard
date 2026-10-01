<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Models\Badge;

final readonly class BadgeMedal
{
    public function __construct(
        public BadgeTier $tier,
        public string $description,
        public string $thresholdLabel,
        public bool $earned,
        public bool $pending,
    ) {}

    /**
     * Build the medal of a catalogue badge with its threshold expressed in the unit of its rule, pending when the live measure reaches an unawarded tier.
     */
    public static function fromBadge(Badge $badge, BadgeRule $rule, bool $earned, float $measure): self
    {
        $threshold = (float) $badge->threshold;

        return new self(
            tier: $badge->tier,
            description: $rule->key()->description($rule->unit()->formatNumber($threshold)),
            thresholdLabel: $rule->unit()->format($threshold),
            earned: $earned,
            pending: ! $earned && $measure >= $threshold,
        );
    }

    /**
     * Get the text alternative announcing the tier and whether the medal is earned, reached or locked.
     */
    public function accessibleLabel(): string
    {
        $state = match (true) {
            $this->earned => __('gamification::badges.showcase.earned'),
            $this->pending => __('gamification::badges.showcase.pending'),
            default => __('gamification::badges.showcase.locked'),
        };

        return __('gamification::badges.showcase.medal', ['tier' => $this->tier->label(), 'state' => $state]);
    }

    /**
     * Get the Tailwind classes of the medal frame, lit with the tier accent once earned and dashed while pending.
     */
    public function frameClass(): string
    {
        return match (true) {
            $this->earned => 'border-(color:--medal) bg-surface-2 text-(color:--medal) shadow-[0_0_14px_var(--medal)]',
            $this->pending => 'border-dashed border-(color:--medal) bg-surface text-(color:--medal)',
            default => 'border-hairline bg-surface text-faint opacity-60',
        };
    }
}

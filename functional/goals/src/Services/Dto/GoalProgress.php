<?php

namespace Functional\Goals\Services\Dto;

readonly class GoalProgress
{
    /**
     * Hold the computed progress figures of a goal against its target.
     */
    public function __construct(
        public float $currentValue,
        public float $targetValue,
        public float $percentage,
        public float $remaining,
        public bool $onTrack,
    ) {}

    /**
     * Build a progress result from a current value measured against a target.
     */
    public static function fromValues(float $currentValue, float $targetValue, bool $onTrack = true): self
    {
        $percentage = $targetValue > 0.0 ? round(($currentValue / $targetValue) * 100, 2) : 0.0;
        $remaining = round(max($targetValue - $currentValue, 0.0), 4);

        return new self(
            round($currentValue, 4),
            round($targetValue, 4),
            max($percentage, 0.0),
            $remaining,
            $onTrack,
        );
    }

    /**
     * Determine whether the goal reached or exceeded its target.
     */
    public function isComplete(): bool
    {
        return $this->percentage >= 100.0;
    }

    /**
     * Expose the percentage clamped to the displayable arc range.
     */
    public function clampedPercentage(): float
    {
        return min($this->percentage, 100.0);
    }

    /**
     * Expose the progress as a serializable array.
     *
     * @return array{current_value: float, target_value: float, percentage: float, remaining: float, on_track: bool}
     */
    public function toArray(): array
    {
        return [
            'current_value' => $this->currentValue,
            'target_value' => $this->targetValue,
            'percentage' => $this->percentage,
            'remaining' => $this->remaining,
            'on_track' => $this->onTrack,
        ];
    }
}

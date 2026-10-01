<?php

namespace Functional\Gamification\Services\Dto;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Challenge;

final readonly class ChallengeCard
{
    private const FULL_PERCENTAGE = 100;

    private const MAX_UNREACHED_PERCENTAGE = 99;

    public function __construct(
        public string $id,
        public ChallengeTemplateKey $template,
        public GamificationDomain $domain,
        public ChallengeStatus $status,
        public float $currentValue,
        public float $targetValue,
        public float $baselineValue,
        public int $xpReward,
        public CarbonImmutable $updatedAt,
        public bool $isRespondable,
    ) {}

    public static function fromChallenge(Challenge $challenge, bool $isRespondable): self
    {
        return new self(
            id: $challenge->id,
            template: $challenge->template_key,
            domain: $challenge->domain,
            status: $challenge->status,
            currentValue: (float) $challenge->current_value,
            targetValue: (float) $challenge->target_value,
            baselineValue: (float) $challenge->baseline_value,
            xpReward: $challenge->xp_reward,
            updatedAt: $challenge->updated_at->toImmutable(),
            isRespondable: $isRespondable,
        );
    }

    public function percentage(): float
    {
        if ($this->targetValue <= 0.0) {
            return self::FULL_PERCENTAGE;
        }

        return min($this->currentValue / $this->targetValue * self::FULL_PERCENTAGE, self::FULL_PERCENTAGE);
    }

    /**
     * Reads 100 only once the target is reached, so a progress rounding up to the target stays one point short.
     */
    public function roundedPercentage(): int
    {
        if ($this->isReached()) {
            return self::FULL_PERCENTAGE;
        }

        return min((int) round($this->percentage()), self::MAX_UNREACHED_PERCENTAGE);
    }

    public function barWidth(): string
    {
        return sprintf('%.2F%%', $this->percentage());
    }

    public function isReached(): bool
    {
        return $this->currentValue >= $this->targetValue;
    }

    public function isAlreadyReached(): bool
    {
        return $this->status === ChallengeStatus::Proposed && $this->isReached();
    }

    public function tracksProgress(): bool
    {
        return match ($this->status) {
            ChallengeStatus::Proposed, ChallengeStatus::Accepted, ChallengeStatus::Completed, ChallengeStatus::Failed => true,
            ChallengeStatus::Declined, ChallengeStatus::Expired => false,
        };
    }

    public function hasEarnedReward(): bool
    {
        return $this->status === ChallengeStatus::Completed;
    }

    public function isClosed(): bool
    {
        return ! in_array($this->status, ChallengeStatus::open(), true);
    }

    public function name(): string
    {
        return $this->template->label();
    }

    public function description(): string
    {
        return $this->template->description($this->targetValue);
    }

    public function progressLabel(): string
    {
        return __('gamification::challenges.board.progress', [
            'current' => $this->template->unit()->formatNumber($this->currentValue),
            'target' => $this->targetLabel(),
        ]);
    }

    public function targetLabel(): string
    {
        return $this->template->unit()->format($this->targetValue);
    }

    public function baselineLabel(): string
    {
        return __('gamification::challenges.board.baseline', ['baseline' => $this->template->unit()->format($this->baselineValue)]);
    }

    public function rewardLabel(): string
    {
        return __('gamification::challenges.board.reward', ['xp' => $this->xpReward]);
    }

    public function updatedLabel(): string
    {
        return __('gamification::challenges.board.updated', ['time' => $this->updatedAt->diffForHumans(['options' => CarbonInterface::JUST_NOW])]);
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function chipClass(): string
    {
        return $this->status->chipClass();
    }

    public function progressAccessibleLabel(): string
    {
        return __('gamification::challenges.board.progress_label', ['name' => $this->name()]);
    }

    public function acceptAccessibleLabel(): string
    {
        return __('gamification::challenges.board.accept_label', ['name' => $this->name()]);
    }

    public function declineAccessibleLabel(): string
    {
        return __('gamification::challenges.board.decline_label', ['name' => $this->name()]);
    }
}

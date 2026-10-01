<?php

namespace Functional\Gamification\Enums;

use Functional\Gamification\Challenges\States\ChallengeStateFactory;

enum ChallengeStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Failed = 'failed';
    case Declined = 'declined';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => ! ChallengeStateFactory::fromStatus($status)->isTerminal(),
        ));
    }

    public function label(): string
    {
        return __("gamification::challenges.statuses.{$this->value}");
    }

    public function chipClass(): string
    {
        return match ($this) {
            self::Proposed => 'border-hairline bg-violet-soft text-violet',
            self::Accepted => 'border-hairline bg-cyan-soft text-cyan',
            self::Completed => 'border-hairline bg-lime-soft text-lime',
            self::Failed => 'border-hairline bg-surface-2 text-muted',
            self::Declined, self::Expired => 'border-hairline bg-surface text-faint',
        };
    }
}

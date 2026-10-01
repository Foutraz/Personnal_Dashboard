<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Challenge;
use Functional\Users\Models\User;

class ReconvergeChallengeXp
{
    public function __construct(private ReconvergeBonusXp $reconvergeBonusXp) {}

    public function handle(User $user): void
    {
        $entries = Challenge::query()
            ->whereBelongsTo($user)
            ->where('status', ChallengeStatus::Completed)
            ->get()
            ->map(fn (Challenge $challenge): array => [
                'domain' => $challenge->domain->value,
                'source_type' => XpSourceType::Challenge->value,
                'source_id' => $challenge->id,
                'points' => $challenge->xp_reward,
                'occurred_at' => $challenge->resolved_at,
            ])
            ->values()
            ->all();

        $this->reconvergeBonusXp->handle($user, XpRuleKey::ChallengeCompleted, $entries, ['domain', 'points', 'occurred_at']);
    }
}

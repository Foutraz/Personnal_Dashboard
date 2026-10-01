<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;

class ReconvergeChallengeXp
{
    public function handle(User $user): void
    {
        $rows = Challenge::query()
            ->whereBelongsTo($user)
            ->where('status', ChallengeStatus::Completed)
            ->get()
            ->map(fn (Challenge $challenge): array => [
                'user_id' => $user->id,
                'domain' => $challenge->domain->value,
                'rule_key' => XpRuleKey::ChallengeCompleted->value,
                'source_type' => XpSourceType::Challenge->value,
                'source_id' => $challenge->id,
                'points' => $challenge->xp_reward,
                'occurred_at' => $challenge->resolved_at,
            ])
            ->values();

        XpEntry::query()
            ->whereBelongsTo($user)
            ->where('rule_key', XpRuleKey::ChallengeCompleted->value)
            ->whereNotIn('source_id', $rows->pluck('source_id'))
            ->delete();

        $rows->chunk(500)->each(function (Collection $chunk): void {
            XpEntry::query()->upsert(
                $chunk->all(),
                ['user_id', 'rule_key', 'source_type', 'source_id'],
                ['domain', 'points', 'occurred_at'],
            );
        });
    }
}

<?php

namespace Functional\Gamification\Services;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;

class WeeklyChallenges
{
    /**
     * Get the challenges of the user for the week, ordered by domain then by template.
     *
     * @return Collection<int, Challenge>
     */
    public function forWeek(User $user, GamificationWeek $week): Collection
    {
        return Challenge::query()
            ->whereBelongsTo($user)
            ->where('week_key', $week->key())
            ->get()
            ->sortBy(fn (Challenge $challenge): int => $this->rank($challenge))
            ->values();
    }

    private function rank(Challenge $challenge): int
    {
        $domainRank = (int) array_search($challenge->domain, GamificationDomain::cases(), true);
        $templateRank = (int) array_search($challenge->template_key, ChallengeTemplateKey::cases(), true);

        return $domainRank * count(ChallengeTemplateKey::cases()) + $templateRank;
    }
}

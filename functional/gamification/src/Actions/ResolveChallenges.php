<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeProgress;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyMetricMeter;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;

class ResolveChallenges
{
    public function __construct(
        private WeeklyMetricMeter $meter,
        private TransitionChallenge $transitionChallenge,
        private GamificationCalendar $calendar,
    ) {}

    /**
     * Measure each open challenge on its own frozen week, move it to its next state and return those this call completed.
     *
     * @return Collection<int, Challenge>
     *
     * @throws InvalidChallengeConfigException|StaleChallengeStatusException|UnboundedGoalMetricException
     */
    public function handle(User $user): Collection
    {
        $closingGraceHours = ChallengeSettings::fromConfig()->closingGraceHours;
        $moment = now();
        $completed = new Collection;

        $openChallenges = Challenge::query()
            ->whereBelongsTo($user)
            ->whereIn('status', ChallengeStatus::open())
            ->orderBy('starts_at')
            ->orderBy('template_key')
            ->get();

        foreach ($openChallenges as $challenge) {
            $week = $this->weekOf($challenge);
            $measured = $this->meter->measure($user, $challenge->metric, $challenge->starts_at, $challenge->ends_at);
            $next = $challenge->state()->evolve(new ChallengeProgress(
                targetReached: $measured >= (float) $challenge->target_value,
                weekEnded: $week->hasEnded($moment),
                gracePassed: $week->isPastGrace($moment, $closingGraceHours),
            ));

            if ($next->status() === $challenge->status) {
                $this->recordProgress($challenge, $measured);

                continue;
            }

            $this->transitionChallenge->handle($challenge, $next, $measured);

            if ($next->status() === ChallengeStatus::Completed) {
                $completed->push($challenge);
            }
        }

        return $completed;
    }

    private function weekOf(Challenge $challenge): GamificationWeek
    {
        $startDate = $challenge->starts_at->toImmutable()->setTimezone($this->calendar->timezone());

        return new GamificationWeek(
            startDate: $startDate,
            startsAt: $challenge->starts_at->toImmutable(),
            endsAt: $challenge->ends_at->toImmutable(),
            isoYear: $startDate->isoWeekYear,
            isoWeek: $startDate->isoWeek,
        );
    }

    private function recordProgress(Challenge $challenge, float $measured): void
    {
        Challenge::query()->whereKey($challenge->getKey())->update(['current_value' => $measured]);
    }
}

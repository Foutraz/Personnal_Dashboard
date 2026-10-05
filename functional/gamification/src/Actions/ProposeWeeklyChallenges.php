<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Challenges\ChallengeTargetCalculator;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\MissingChallengeTemplateConfigException;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\ChallengeTarget;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\WeeklyMetricMeter;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;

class ProposeWeeklyChallenges
{
    public function __construct(
        private WeeklyMetricMeter $meter,
        private ChallengeTargetCalculator $calculator,
    ) {}

    /**
     * Propose one challenge per domain with an eligible template unless the user already has a set for the week, and return those this call created.
     *
     * @return Collection<int, Challenge>
     *
     * @throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException
     */
    public function handle(User $user, GamificationWeek $week): Collection
    {
        $settings = ChallengeSettings::fromConfig();

        if ($this->hasSet($user, $week)) {
            return new Collection;
        }

        $moment = now()->toDateTimeString();
        $targets = $this->eligibleTargets($user, $week, $settings);
        $rows = collect($this->pickedTemplates($targets, $week))
            ->map(fn (ChallengeTemplateKey $template): array => $this->row($user, $week, $template, $targets[$template->value], $moment, $settings))
            ->values();

        if ($rows->isEmpty()) {
            return new Collection;
        }

        Challenge::query()->insertOrIgnore($rows->all());

        $positions = array_flip($rows->pluck('id')->all());

        return Challenge::query()
            ->whereIn('id', array_keys($positions))
            ->get()
            ->sortBy(fn (Challenge $challenge): int => $positions[$challenge->id])
            ->values();
    }

    private function hasSet(User $user, GamificationWeek $week): bool
    {
        return Challenge::query()
            ->whereBelongsTo($user)
            ->where('week_key', $week->key())
            ->exists();
    }

    /**
     * @param  array<string, ChallengeTarget>  $targets
     * @return list<ChallengeTemplateKey>
     */
    private function pickedTemplates(array $targets, GamificationWeek $week): array
    {
        $picked = [];

        foreach (GamificationDomain::cases() as $domain) {
            $eligible = array_values(array_filter(
                ChallengeTemplateKey::forDomain($domain),
                fn (ChallengeTemplateKey $template): bool => isset($targets[$template->value]),
            ));
            $template = ChallengeTemplateKey::pickFor($eligible, $week->isoWeek);

            if ($template !== null) {
                $picked[] = $template;
            }
        }

        return $picked;
    }

    /**
     * Measure the whole history of each template, then the single weeks of those with data only.
     *
     * @return array<string, ChallengeTarget>
     *
     * @throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException
     */
    private function eligibleTargets(User $user, GamificationWeek $week, ChallengeSettings $settings): array
    {
        $targets = [];

        foreach (ChallengeTemplateKey::cases() as $template) {
            if (! $this->hasHistory($user, $template, $week, $settings)) {
                continue;
            }

            $target = $this->calculator->target($template->settings(), $settings, $this->weeklyValues($user, $template, $week, $settings));

            if ($target !== null) {
                $targets[$template->value] = $target;
            }
        }

        return $targets;
    }

    private function hasHistory(User $user, ChallengeTemplateKey $template, GamificationWeek $week, ChallengeSettings $settings): bool
    {
        $historyStartsAt = $week->previous($settings->historyWeeks)->startsAt;

        return $this->meter->measure($user, $template->metric(), $historyStartsAt, $week->startsAt) > 0.0;
    }

    /**
     * @return list<float>
     */
    private function weeklyValues(User $user, ChallengeTemplateKey $template, GamificationWeek $week, ChallengeSettings $settings): array
    {
        return array_map(
            fn (int $weeksBack): float => $this->meter->measureWeek($user, $template->metric(), $week->previous($weeksBack)),
            range($settings->historyWeeks, 1),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user, GamificationWeek $week, ChallengeTemplateKey $template, ChallengeTarget $target, string $moment, ChallengeSettings $settings): array
    {
        return [
            'id' => (new Challenge)->newUniqueId(),
            'user_id' => $user->id,
            'week_key' => $week->key(),
            'template_key' => $template->value,
            'domain' => $template->domain()->value,
            'metric' => $template->metric()->value,
            'starts_at' => $week->startsAt->toDateTimeString(),
            'ends_at' => $week->endsAt->toDateTimeString(),
            'closes_at' => $week->closesAt($settings->closingGraceHours)->toDateTimeString(),
            'baseline_value' => $target->baseline,
            'target_value' => $target->target,
            'current_value' => 0,
            'xp_reward' => $settings->xpReward,
            'status' => ChallengeStatus::Proposed->value,
            'accepted_at' => null,
            'resolved_at' => null,
            'created_at' => $moment,
            'updated_at' => $moment,
        ];
    }
}

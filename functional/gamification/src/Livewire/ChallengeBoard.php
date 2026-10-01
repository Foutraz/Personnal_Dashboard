<?php

namespace Functional\Gamification\Livewire;

use Closure;
use Functional\Gamification\Actions\RespondToChallenge;
use Functional\Gamification\Exceptions\ChallengeNotRespondableException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeCard;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyChallenges;
use Functional\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ChallengeBoard extends Component
{
    #[Locked]
    public string $announcement = '';

    public function accept(string $challengeId, RespondToChallenge $respond): void
    {
        $challenge = $this->ownedChallenge($challengeId);

        $this->respondWith(fn () => $respond->accept($challenge));

        $this->announcement = __('gamification::challenges.board.accepted_announcement', ['name' => $challenge->template_key->label()]);
    }

    public function decline(string $challengeId, RespondToChallenge $respond): void
    {
        $challenge = $this->ownedChallenge($challengeId);

        $this->respondWith(fn () => $respond->decline($challenge));

        $this->announcement = __('gamification::challenges.board.declined_announcement', ['name' => $challenge->template_key->label()]);
    }

    public function render(WeeklyChallenges $weeklyChallenges, GamificationCalendar $calendar, RespondToChallenge $respond): View
    {
        /** @var User $user */
        $user = Auth::user();
        $week = $calendar->currentWeek();
        $toCard = fn (Challenge $challenge): ChallengeCard => ChallengeCard::fromChallenge($challenge, $respond->isRespondable($challenge));

        return view('gamification::challenge-board', [
            'subtitle' => $this->subtitle($week),
            'currentCards' => $weeklyChallenges->forWeek($user, $week)->map($toCard),
            'previousCards' => $weeklyChallenges->forWeek($user, $week->previous())->map($toCard),
            'historyWeeks' => ChallengeSettings::fromConfig()->historyWeeks,
        ]);
    }

    private function ownedChallenge(string $challengeId): Challenge
    {
        /** @var User $user */
        $user = Auth::user();
        $challenge = Challenge::query()->whereBelongsTo($user)->findOrFail($challengeId);

        $this->authorize('respond', $challenge);

        return $challenge;
    }

    private function respondWith(Closure $response): void
    {
        rescue(
            $response,
            fn (Throwable $exception): never => throw $this->asNotRespondable($exception),
            report: false,
        );
    }

    /**
     * @throws Throwable
     */
    private function asNotRespondable(Throwable $exception): ChallengeNotRespondableException
    {
        if (! $exception instanceof StaleChallengeStatusException) {
            throw $exception;
        }

        return new ChallengeNotRespondableException;
    }

    private function subtitle(GamificationWeek $week): string
    {
        $dateFormat = __('gamification::challenges.board.week_date_format');
        $lastDay = $week->lastMoment()->setTimezone($week->startDate->getTimezone());

        return __('gamification::challenges.board.subtitle', [
            'start' => $week->startDate->translatedFormat($dateFormat),
            'end' => $lastDay->translatedFormat($dateFormat),
        ]);
    }
}

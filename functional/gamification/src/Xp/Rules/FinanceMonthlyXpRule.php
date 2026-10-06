<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Finance\Models\BankTransaction;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Services\CapitalCalculator;
use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Services\Dto\FinanceXpSettings;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceMonthlyXpRule implements XpRule
{
    private const MONTH_FORMAT = 'Y-m';

    public function __construct(private CapitalCalculator $capital) {}

    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return XpRuleKey::FinanceMonth->value;
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Finance;
    }

    /**
     * Award one entry per elapsed month of positive savings or real investing, re-evaluating every month so late-booked data corrects the ledger.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $settings = FinanceXpSettings::fromConfig();
        $currentMonthStart = now()->startOfMonth();

        $savingsByMonth = BankTransaction::query()
            ->where('user_id', $user->id)
            ->where('booked_at', '<', $currentMonthStart)
            ->get(['id', 'amount', 'booked_at'])
            ->groupBy(fn (BankTransaction $transaction): string => $transaction->booked_at->format(self::MONTH_FORMAT))
            ->map(fn (Collection $transactions): float => (float) $transactions->sum('amount'));

        $investmentMonths = $this->qualifyingInvestmentMonths($user, $currentMonthStart, $settings->investmentMinimumNetBought);

        return $savingsByMonth->keys()
            ->merge($investmentMonths)
            ->unique()
            ->sort()
            ->map(fn (int|string $month): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: XpSourceType::Period->value,
                sourceId: (string) $month,
                points: $this->points($savingsByMonth->get($month, 0.0), $investmentMonths->contains((string) $month), $settings),
                occurredAt: Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay(),
            ))
            ->filter(fn (XpAward $award): bool => $award->points > 0)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function qualifyingInvestmentMonths(User $user, Carbon $currentMonthStart, float $minimumNetBought): Collection
    {
        $accountMonth = $user->created_at?->format(self::MONTH_FORMAT) ?? '';

        return InvestmentTransaction::query()
            ->where('user_id', $user->id)
            ->where('executed_at', '<', $currentMonthStart)
            ->with('position:id,created_at')
            ->get(['id', 'position_id', 'type', 'quantity', 'unit_price', 'executed_at'])
            ->filter(fn (InvestmentTransaction $transaction): bool => $this->accountAndPositionExisted($transaction, $accountMonth))
            ->groupBy(fn (InvestmentTransaction $transaction): string => $transaction->executed_at->format(self::MONTH_FORMAT))
            ->filter(fn (Collection $transactions): bool => $this->capital->netInvested($transactions) >= $minimumNetBought)
            ->keys()
            ->map(fn (int|string $month): string => (string) $month);
    }

    private function accountAndPositionExisted(InvestmentTransaction $transaction, string $accountMonth): bool
    {
        $positionCreatedAt = $transaction->position?->created_at;

        if ($positionCreatedAt === null) {
            return false;
        }

        $executionMonth = $transaction->executed_at->format(self::MONTH_FORMAT);

        return $executionMonth >= $accountMonth && $executionMonth >= $positionCreatedAt->format(self::MONTH_FORMAT);
    }

    /**
     * Compute the month points from its net savings and investment activity.
     */
    private function points(float $netSavings, bool $invested, FinanceXpSettings $settings): int
    {
        $savingsPoints = $netSavings > 0 ? $settings->positiveSavingsMonth : 0;
        $investmentPoints = $invested ? $settings->investmentContributionMonth : 0;

        return $savingsPoints + $investmentPoints;
    }
}

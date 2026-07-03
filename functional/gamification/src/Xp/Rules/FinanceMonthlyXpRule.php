<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\BankTransaction;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceMonthlyXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'finance_month';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Finance;
    }

    /**
     * Award one entry per elapsed month of positive savings or investing, re-evaluating every month so late-booked data corrects the ledger.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.finance');
        $currentMonthStart = now()->startOfMonth();

        $savingsByMonth = BankTransaction::query()
            ->where('user_id', $user->id)
            ->where('booked_at', '<', $currentMonthStart)
            ->get(['id', 'amount', 'booked_at'])
            ->groupBy(fn (BankTransaction $transaction): string => $transaction->booked_at->format('Y-m'))
            ->map(fn (Collection $transactions): float => (float) $transactions->sum('amount'));

        $investmentMonths = InvestmentTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Buy)
            ->where('executed_at', '<', $currentMonthStart)
            ->get(['id', 'executed_at'])
            ->map(fn (InvestmentTransaction $transaction): string => $transaction->executed_at->format('Y-m'))
            ->unique();

        return $savingsByMonth->keys()
            ->merge($investmentMonths)
            ->unique()
            ->sort()
            ->map(fn (int|string $month): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: 'period',
                sourceId: (string) $month,
                points: $this->points($savingsByMonth->get($month, 0.0), $investmentMonths->contains((string) $month), $config),
                occurredAt: Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay(),
            ))
            ->filter(fn (XpAward $award): bool => $award->points > 0)
            ->values();
    }

    /**
     * Compute the month points from its net savings and investment activity.
     *
     * @param  array<string, int>  $config
     */
    private function points(float $netSavings, bool $invested, array $config): int
    {
        $savingsPoints = $netSavings > 0 ? $config['positive_savings_month'] : 0;
        $investmentPoints = $invested ? $config['investment_contribution_month'] : 0;

        return $savingsPoints + $investmentPoints;
    }
}

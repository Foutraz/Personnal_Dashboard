<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Services\CapitalCalculator;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Users\Models\User;

class FinanceInvestedCapitalBadgeRule implements BadgeRule
{
    /**
     * Create the rule with the finance capital calculator.
     */
    public function __construct(private CapitalCalculator $capital) {}

    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): BadgeRuleKey
    {
        return BadgeRuleKey::FinanceInvestedCapital;
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Finance;
    }

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): BadgeUnit
    {
        return BadgeUnit::Euros;
    }

    /**
     * Measure the user's net invested capital through the finance capital calculator.
     */
    public function measure(User $user): float
    {
        return $this->capital->netInvested(InvestmentTransaction::query()->whereBelongsTo($user)->get());
    }
}

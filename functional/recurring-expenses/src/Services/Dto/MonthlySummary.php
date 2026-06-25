<?php

namespace Functional\RecurringExpenses\Services\Dto;

class MonthlySummary
{
    /**
     * Build the normalized monthly summary of the recurring expenses.
     *
     * @param  array<string, float>  $perCategory
     */
    public function __construct(
        public float $monthlyTotal,
        public float $yearlyTotal,
        public array $perCategory,
    ) {}
}

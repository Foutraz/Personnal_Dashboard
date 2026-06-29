<?php

namespace Tests\Unit\RecurringExpenses;

use Carbon\CarbonImmutable;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Services\NextDueDateCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NextDueDateCalculatorTest extends TestCase
{
    private NextDueDateCalculator $calculator;

    /**
     * Prepare a fresh calculator for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new NextDueDateCalculator;
    }

    #[Test]
    public function it_advances_a_weekly_due_date_to_the_next_future_occurrence(): void
    {
        $now = CarbonImmutable::parse('2026-06-25 12:00:00');
        $current = CarbonImmutable::parse('2026-06-10 09:00:00');

        $next = $this->calculator->advance(ExpenseFrequency::Weekly, $current, null, $now);

        $this->assertSame('2026-07-01', $next->toDateString());
    }

    #[Test]
    public function it_advances_a_monthly_due_date_clamping_the_due_day(): void
    {
        $now = CarbonImmutable::parse('2026-06-25 12:00:00');
        $current = CarbonImmutable::parse('2026-06-05 09:00:00');

        $next = $this->calculator->advance(ExpenseFrequency::Monthly, $current, 5, $now);

        $this->assertSame('2026-07-05', $next->toDateString());
    }

    #[Test]
    public function it_advances_a_quarterly_due_date(): void
    {
        $now = CarbonImmutable::parse('2026-06-25 12:00:00');
        $current = CarbonImmutable::parse('2026-04-01 00:00:00');

        $next = $this->calculator->advance(ExpenseFrequency::Quarterly, $current, null, $now);

        $this->assertSame('2026-07-01', $next->toDateString());
    }

    #[Test]
    public function it_advances_a_yearly_due_date(): void
    {
        $now = CarbonImmutable::parse('2026-06-25 12:00:00');
        $current = CarbonImmutable::parse('2025-07-01 00:00:00');

        $next = $this->calculator->advance(ExpenseFrequency::Yearly, $current, null, $now);

        $this->assertSame('2026-07-01', $next->toDateString());
    }

    #[Test]
    public function it_clamps_the_due_day_to_the_last_day_of_a_short_month(): void
    {
        $now = CarbonImmutable::parse('2026-01-31 12:00:00');
        $current = CarbonImmutable::parse('2026-01-31 00:00:00');

        $next = $this->calculator->advance(ExpenseFrequency::Monthly, $current, 31, $now);

        $this->assertSame('2026-02-28', $next->toDateString());
    }

    #[Test]
    public function it_computes_the_first_monthly_due_date_on_the_requested_day(): void
    {
        $first = $this->calculator->firstDueDate(
            ExpenseFrequency::Monthly,
            CarbonImmutable::parse('2026-06-10'),
            15,
        );

        $this->assertSame('2026-06-15', $first->toDateString());
    }

    #[Test]
    public function it_projects_a_monthly_occurrence_into_a_later_month(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Monthly,
            CarbonImmutable::parse('2026-06-15'),
            CarbonImmutable::parse('2026-08-01'),
            15,
        );

        $this->assertSame(['2026-08-15'], $this->toDates($occurrences));
    }

    #[Test]
    public function it_projects_every_weekly_occurrence_within_a_later_month(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Weekly,
            CarbonImmutable::parse('2026-06-03'),
            CarbonImmutable::parse('2026-07-01'),
        );

        $this->assertSame(
            ['2026-07-01', '2026-07-08', '2026-07-15', '2026-07-22', '2026-07-29'],
            $this->toDates($occurrences),
        );
    }

    #[Test]
    public function it_projects_a_quarterly_occurrence_into_a_later_month(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Quarterly,
            CarbonImmutable::parse('2026-04-01'),
            CarbonImmutable::parse('2026-07-01'),
        );

        $this->assertSame(['2026-07-01'], $this->toDates($occurrences));
    }

    #[Test]
    public function it_projects_a_yearly_occurrence_into_a_later_year(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Yearly,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2027-07-01'),
        );

        $this->assertSame(['2027-07-01'], $this->toDates($occurrences));
    }

    #[Test]
    public function it_does_not_project_occurrences_before_the_start_date(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Monthly,
            CarbonImmutable::parse('2026-06-15'),
            CarbonImmutable::parse('2026-05-01'),
            15,
        );

        $this->assertSame([], $this->toDates($occurrences));
    }

    #[Test]
    public function it_does_not_project_occurrences_after_the_end_date(): void
    {
        $occurrences = $this->calculator->occurrencesInMonth(
            ExpenseFrequency::Monthly,
            CarbonImmutable::parse('2026-06-15'),
            CarbonImmutable::parse('2026-08-01'),
            15,
            CarbonImmutable::parse('2026-07-31'),
        );

        $this->assertSame([], $this->toDates($occurrences));
    }

    /**
     * Reduce a list of occurrences to their date strings for readable assertions.
     *
     * @param  array<int, CarbonImmutable>  $occurrences
     * @return array<int, string>
     */
    private function toDates(array $occurrences): array
    {
        return array_map(fn (CarbonImmutable $occurrence): string => $occurrence->toDateString(), $occurrences);
    }
}

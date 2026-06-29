<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Livewire\ExpensesDashboard;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpensesDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_an_expense_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->set('newLabel', 'Loyer')
            ->set('newAmount', '950')
            ->set('newCategory', ExpenseCategory::Rent->value)
            ->set('newFrequency', ExpenseFrequency::Monthly->value)
            ->set('newDueDay', '5')
            ->set('newNextDueAt', now()->addWeek()->toDateString())
            ->call('createExpense')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_expenses', [
            'label' => 'Loyer',
            'user_id' => $user->id,
            'category' => ExpenseCategory::Rent->value,
            'frequency' => ExpenseFrequency::Monthly->value,
        ]);
    }

    #[Test]
    public function it_validates_the_label_and_amount_are_required(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->set('newLabel', '')
            ->set('newAmount', '')
            ->call('createExpense')
            ->assertHasErrors(['newLabel' => 'required', 'newAmount' => 'required']);
    }

    #[Test]
    public function it_renders_the_calendar_for_the_current_month(): void
    {
        $user = User::factory()->create();
        RecurringExpense::factory()->create([
            'user_id' => $user->id,
            'label' => 'Assurance auto',
            'next_due_at' => now()->startOfMonth()->addDays(9),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->assertSee('Assurance auto')
            ->assertSee('Calendrier des échéances');
    }

    #[Test]
    public function it_projects_a_monthly_expense_onto_the_following_months_calendar(): void
    {
        $this->travelTo(Carbon::parse('2026-06-29'));

        $user = User::factory()->create();
        RecurringExpense::factory()->create([
            'user_id' => $user->id,
            'label' => 'Loyer',
            'frequency' => ExpenseFrequency::Monthly,
            'due_day' => 15,
            'starts_at' => '2026-06-15',
            'next_due_at' => '2026-06-15',
            'active' => true,
        ]);

        $component = Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('nextMonth')
            ->call('nextMonth');

        $this->assertContains('Loyer', $this->calendarLabelsForDay($component->viewData('calendar'), 15));
    }

    #[Test]
    public function it_projects_every_weekly_occurrence_onto_the_following_months_calendar(): void
    {
        $this->travelTo(Carbon::parse('2026-06-29'));

        $user = User::factory()->create();
        RecurringExpense::factory()->create([
            'user_id' => $user->id,
            'label' => 'Abonnement',
            'frequency' => ExpenseFrequency::Weekly,
            'due_day' => null,
            'starts_at' => '2026-06-03',
            'next_due_at' => '2026-07-01',
            'active' => true,
        ]);

        $component = Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('nextMonth');

        $calendar = $component->viewData('calendar');

        foreach ([1, 8, 15, 22, 29] as $day) {
            $this->assertContains('Abonnement', $this->calendarLabelsForDay($calendar, $day));
        }
    }

    /**
     * Collect the expense labels rendered on the given day of the calendar grid.
     *
     * @param  array{weeks: array<int, array<int, array{day: int|null, expenses: array<int, RecurringExpense>}>>}  $calendar
     * @return array<int, string>
     */
    private function calendarLabelsForDay(array $calendar, int $day): array
    {
        foreach ($calendar['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell['day'] === $day) {
                    return array_map(fn (RecurringExpense $expense): string => $expense->label, $cell['expenses']);
                }
            }
        }

        return [];
    }

    #[Test]
    public function it_navigates_to_the_next_month(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('nextMonth')
            ->assertSet('calendarMonth', now()->addMonthNoOverflow()->startOfMonth()->toDateString());
    }

    #[Test]
    public function it_toggles_the_active_state_of_an_expense(): void
    {
        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true]);

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('toggleActive', $expense->id);

        $this->assertFalse($expense->fresh()->active);
    }

    #[Test]
    public function it_deletes_an_expense(): void
    {
        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('deleteExpense', $expense->id);

        $this->assertSoftDeleted('recurring_expenses', ['id' => $expense->id]);
    }

    #[Test]
    public function it_does_not_delete_another_users_expense(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $expense = RecurringExpense::factory()->create(['user_id' => $other->id]);

        Livewire::actingAs($user, 'web')
            ->test(ExpensesDashboard::class)
            ->call('deleteExpense', $expense->id);

        $this->assertDatabaseHas('recurring_expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }
}

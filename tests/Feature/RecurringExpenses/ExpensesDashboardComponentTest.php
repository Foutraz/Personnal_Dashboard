<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Livewire\ExpensesDashboard;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpensesApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_expenses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = RecurringExpense::factory()->count(2)->create(['user_id' => $user->id]);
        RecurringExpense::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($own->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_authenticated_user_when_creating_an_expense(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'label' => 'Netflix',
                        'amount' => 13.49,
                        'currency' => 'EUR',
                        'category' => ExpenseCategory::Subscription->value,
                        'frequency' => ExpenseFrequency::Monthly->value,
                        'next_due_at' => now()->addWeek()->toDateTimeString(),
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('recurring_expenses', [
            'label' => 'Netflix',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_forbids_deleting_another_users_expense(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $expense = RecurringExpense::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'api')->deleteJson('/api/recurring-expenses', [
            'resources' => [$expense->id],
        ]);

        $this->assertDatabaseHas('recurring_expenses', [
            'id' => $expense->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_forbids_updating_another_users_expense(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $expense = RecurringExpense::factory()->create(['user_id' => $other->id, 'label' => 'Original']);

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $expense->id, 'attributes' => ['label' => 'Hijacked']],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('recurring_expenses', [
            'id' => $expense->id,
            'label' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_and_delete_their_expense(): void
    {
        $user = User::factory()->create();

        $expense = RecurringExpense::factory()->create(['user_id' => $user->id, 'label' => 'Original']);

        $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $expense->id, 'attributes' => ['label' => 'Updated']],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('recurring_expenses', ['id' => $expense->id, 'label' => 'Updated']);

        $this->actingAs($user, 'api')->deleteJson('/api/recurring-expenses', [
            'resources' => [$expense->id],
        ])->assertOk();

        $this->assertSoftDeleted('recurring_expenses', ['id' => $expense->id]);
    }
}

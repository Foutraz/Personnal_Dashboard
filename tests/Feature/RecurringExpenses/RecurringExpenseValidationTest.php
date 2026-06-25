<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpenseValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_a_negative_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'label' => 'Rent',
                        'amount' => -800,
                        'category' => ExpenseCategory::Rent->value,
                        'frequency' => 'monthly',
                        'next_due_at' => '2026-07-01',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.amount']);
    }

    #[Test]
    public function it_rejects_a_due_day_out_of_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'label' => 'Rent',
                        'amount' => 800,
                        'category' => ExpenseCategory::Rent->value,
                        'frequency' => 'monthly',
                        'next_due_at' => '2026-07-01',
                        'due_day' => 45,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.due_day']);
    }

    #[Test]
    public function it_rejects_an_invalid_frequency(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/recurring-expenses/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'label' => 'Rent',
                        'amount' => 800,
                        'category' => ExpenseCategory::Rent->value,
                        'frequency' => 'banana',
                        'next_due_at' => '2026-07-01',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.frequency']);
    }
}

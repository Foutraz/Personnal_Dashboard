<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PositionDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_positions_transactions_when_the_position_is_deleted(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);
        $transaction = InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $otherPosition = Position::factory()->create(['user_id' => $user->id]);
        $otherTransaction = InvestmentTransaction::factory()->create([
            'position_id' => $otherPosition->id,
            'user_id' => $user->id,
        ]);

        $position->delete();

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
        $this->assertSoftDeleted('investment_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('investment_transactions', ['id' => $otherTransaction->id, 'deleted_at' => null]);
    }
}

<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_users_positions_and_transactions_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);
        $transaction = InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $other = User::factory()->create();
        $otherPosition = Position::factory()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
        $this->assertSoftDeleted('investment_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('positions', ['id' => $otherPosition->id, 'deleted_at' => null]);
    }
}

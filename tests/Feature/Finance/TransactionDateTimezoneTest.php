<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionDateTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->position = Position::factory()->for($this->owner)->create();
    }

    private function createTransaction(string $executedAt): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'position_id' => $this->position->id,
                    'type' => TransactionType::Buy->value,
                    'quantity' => 2,
                    'unit_price' => 150.5,
                    'executed_at' => $executedAt,
                ],
            ]],
        ]);
    }

    private function updateTransaction(InvestmentTransaction $transaction, string $executedAt): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $transaction->id,
                'attributes' => ['executed_at' => $executedAt],
            ]],
        ]);
    }

    private function storedExecutedAt(): string
    {
        return InvestmentTransaction::query()->sole()->getRawOriginal('executed_at');
    }

    public static function acceptedZonedExecutions(): array
    {
        return [
            'positive offset equal to the past in utc' => ['2026-10-01T23:00:00+14:00', '2026-10-01 09:00:00'],
            'negative offset equal to now in utc' => ['2026-10-01T05:00:00-05:00', '2026-10-01 10:00:00'],
            'zone identifier equal to the past in utc' => ['2026-10-01 23:00:00 Pacific/Kiritimati', '2026-10-01 09:00:00'],
            'offset epoch floor in utc' => ['1970-01-01T00:00:00-14:00', '1970-01-01 14:00:00'],
            'zulu marker' => ['2026-10-01T10:00:00Z', '2026-10-01 10:00:00'],
            'no offset read as utc' => ['2026-09-14 08:00:00', '2026-09-14 08:00:00'],
        ];
    }

    public static function rejectedZonedExecutions(): array
    {
        return [
            'positive offset one second after now in utc' => ['2026-10-02T00:00:01+14:00'],
            'zone identifier one second after now in utc' => ['2026-10-02 00:00:01 Pacific/Kiritimati'],
            'negative offset one second after now in utc' => ['2026-10-01T05:00:01-05:00'],
            'offset equal to the epoch in utc' => ['1970-01-01T14:00:00+14:00'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedZonedExecutions')]
    public function it_stores_the_utc_wall_clock_of_a_zoned_execution_created_through_the_api(string $executedAt, string $stored): void
    {
        $this->createTransaction($executedAt)->assertOk();

        $this->assertSame($stored, $this->storedExecutedAt());
    }

    #[Test]
    #[DataProvider('rejectedZonedExecutions')]
    public function it_rejects_a_zoned_execution_after_now_in_utc_through_the_api(string $executedAt): void
    {
        $response = $this->createTransaction($executedAt);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.executed_at']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_stores_the_utc_wall_clock_of_a_zoned_execution_set_through_an_api_update(): void
    {
        $transaction = InvestmentTransaction::factory()->for($this->position)->for($this->owner)->create([
            'executed_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $this->updateTransaction($transaction, '2026-10-01T23:00:00+14:00')->assertOk();

        $this->assertSame('2026-10-01 09:00:00', $this->storedExecutedAt());
    }

    #[Test]
    public function it_rejects_a_zoned_execution_after_now_in_utc_through_an_api_update(): void
    {
        $transaction = InvestmentTransaction::factory()->for($this->position)->for($this->owner)->create([
            'executed_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $response = $this->updateTransaction($transaction, '2026-10-02T00:00:01+14:00');

        $response->assertUnprocessable();
        $this->assertSame('2026-09-20 08:00:00', $this->storedExecutedAt());
    }
}

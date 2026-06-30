<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Actions\RefreshPositionPrices;
use Functional\Finance\Enums\AssetType;
use Functional\Finance\Models\Position;
use Functional\Finance\Services\MarketPriceService;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshPositionPricesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_current_price_for_crypto_positions_and_ignores_stocks(): void
    {
        $user = User::factory()->create();

        $btc = Position::factory()->create([
            'user_id' => $user->id,
            'asset_symbol' => 'BTC',
            'asset_type' => AssetType::Crypto,
            'current_price' => null,
        ]);

        $eth = Position::factory()->create([
            'user_id' => $user->id,
            'asset_symbol' => 'ETH',
            'asset_type' => AssetType::Crypto,
            'current_price' => null,
        ]);

        $aapl = Position::factory()->create([
            'user_id' => $user->id,
            'asset_symbol' => 'AAPL',
            'asset_type' => AssetType::Stock,
            'current_price' => null,
        ]);

        $fakeService = new class extends MarketPriceService
        {
            public function __construct() {}

            /** @return array<string, float> */
            public function pricesFor(array $coingeckoIds, string $vsCurrency = 'eur'): array
            {
                return ['bitcoin' => 58000.0, 'ethereum' => 3200.0];
            }
        };

        $this->app->instance(MarketPriceService::class, $fakeService);

        $count = app(RefreshPositionPrices::class)();

        $this->assertSame(2, $count);
        $this->assertEqualsWithDelta(58000.0, (float) $btc->fresh()->current_price, 0.001);
        $this->assertEqualsWithDelta(3200.0, (float) $eth->fresh()->current_price, 0.001);
        $this->assertNull($aapl->fresh()->current_price);
    }

    #[Test]
    public function it_is_idempotent_when_run_twice(): void
    {
        $user = User::factory()->create();

        $btc = Position::factory()->create([
            'user_id' => $user->id,
            'asset_symbol' => 'BTC',
            'asset_type' => AssetType::Crypto,
            'current_price' => null,
        ]);

        $fakeService = new class extends MarketPriceService
        {
            public function __construct() {}

            /** @return array<string, float> */
            public function pricesFor(array $coingeckoIds, string $vsCurrency = 'eur'): array
            {
                return ['bitcoin' => 58000.0];
            }
        };

        $this->app->instance(MarketPriceService::class, $fakeService);

        app(RefreshPositionPrices::class)();
        app(RefreshPositionPrices::class)();

        $this->assertEqualsWithDelta(58000.0, (float) $btc->fresh()->current_price, 0.001);
    }
}

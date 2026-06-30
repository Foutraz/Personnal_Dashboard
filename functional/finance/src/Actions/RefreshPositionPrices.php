<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Models\Position;
use Functional\Finance\Services\MarketPriceService;

class RefreshPositionPrices
{
    /**
     * Refresh the current_price of every crypto position from the market data API.
     */
    public function __construct(public MarketPriceService $priceService) {}

    /**
     * Update current_price on all crypto positions and return the number of positions updated.
     */
    public function __invoke(): int
    {
        $positions = Position::query()
            ->where('asset_type', AssetType::Crypto)
            ->get();

        $idMap = $positions->mapWithKeys(function (Position $position): array {
            $id = config('finance.marketdata.coingecko_ids.'.$position->asset_symbol)
                ?? strtolower($position->asset_symbol);

            return [$position->id => $id];
        });

        $uniqueIds = $idMap->values()->unique()->values()->all();

        $prices = $this->priceService->pricesFor($uniqueIds);

        $updated = 0;

        foreach ($positions as $position) {
            $coinId = $idMap->get($position->id);

            if ($coinId !== null && isset($prices[$coinId])) {
                $position->forceFill(['current_price' => $prices[$coinId]])->save();
                $updated++;
            }
        }

        return $updated;
    }
}

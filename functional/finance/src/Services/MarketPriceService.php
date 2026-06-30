<?php

namespace Functional\Finance\Services;

use Foutraz\MarketData\MarketDataManager;
use Illuminate\Support\Facades\Cache;

class MarketPriceService
{
    /**
     * Resolve current market prices for the given CoinGecko IDs.
     */
    public function __construct(public MarketDataManager $manager) {}

    /**
     * Return cached market prices for the given CoinGecko coin IDs in the requested currency.
     *
     * @param  array<int, string>  $coingeckoIds
     * @return array<string, float>
     */
    public function pricesFor(array $coingeckoIds, string $vsCurrency = 'eur'): array
    {
        if (empty($coingeckoIds)) {
            return [];
        }

        $sortedIds = $coingeckoIds;
        sort($sortedIds);

        return Cache::remember(
            'finance:marketdata:'.$vsCurrency.':'.implode(',', $sortedIds),
            (int) config('finance.marketdata.cache_ttl', 900),
            fn (): array => $this->manager->prices()->prices($coingeckoIds, $vsCurrency),
        );
    }
}

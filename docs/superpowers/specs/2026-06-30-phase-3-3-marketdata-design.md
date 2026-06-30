# Phase 3.3 — Intégration MarketData (cours crypto en temps réel)

> Design auto-validé le 2026-06-30 (mode autonome). Dernière intégration de la Phase 3.
> Branche `feature/phase-3-3-marketdata`, **empilée sur** `feature/phase-3-2-gocardless`.

## 1. Contexte & principe

Valorise les positions Finance en temps quasi-réel. **Intégration la plus légère** : clé API seule (pas d'OAuth, pas de connexion par utilisateur, pas de modèle). Un job planifié rafraîchit le cours des positions et met à jour `Position.current_price` (déjà le champ qui pilote la valorisation dans `PerformanceCalculator`), via un service caché. La tuile Finance existante (`FinanceDashboardContribution`, ordre 20) devient « vivante » sans modification.

Bâti en mirror du **SDK_Weather** (le template key-only existant) + du **WeatherForecastService** (pattern de cache). Construit et **testé sans credentials réels** (HTTP mocké ; CoinGecko free tier ne requiert pas de clé).

## 2. Décisions de scope (mode autonome)

| # | Décision | Raison |
|---|----------|--------|
| P33-D1 | SDK `Foutraz/SDK_MarketData` (`foutraz/market-data`), package `path` sibling + symlink | Mirror SDK_Weather (key-only). |
| P33-D2 | **Crypto via CoinGecko uniquement** ; **actions reportées** | CoinGecko = crypto ; les cours actions exigent une autre API keyée — slot futur. |
| P33-D3 | Met à jour `Position.current_price` (pas de nouveau modèle) | C'est le champ de valorisation existant ; rend la tuile Finance live. |
| P33-D4 | **Pas de connexion par utilisateur, pas de controller, pas de nouvelle tuile** | Clé applicative ; rafraîchissement planifié global. `DashboardStatsTest` inchangé (10). |
| P33-D5 | Mapping symbole→id CoinGecko via config map + fallback `strtolower(asset_symbol)` | CoinGecko utilise des ids (`bitcoin`) pas des tickers (`BTC`). |
| P33-D6 | Cache `Cache::remember` (TTL config) sur les cours ; HTTP mocké en test | Mirror WeatherForecastService ; évite de marteler l'API. |

## 3. Périmètre

**Dans le périmètre :**
- SDK `Foutraz/SDK_MarketData` : `MarketDataManager(string $endpoint, string $apiKey, ?ClientInterface $client = null)` (mirror WeatherManager, key-only) + `Actions\ManagesPrices` (`prices(array $ids, string $vsCurrency = 'eur'): array<string, float>` → GET `/api/v3/simple/price?ids=…&vs_currencies=…`, clé optionnelle via header `x-cg-demo-api-key`) + `Concerns\MakesHttpRequests` + exceptions + provider. Tests mockés.
- `functional/finance` : `Services\MarketPriceService` (injecte `MarketDataManager` ; `pricesFor(array $coingeckoIds): array<string,float>` via `Cache::remember(key, config('finance.marketdata.cache_ttl'), …)` ; garde `isConfigured()` non bloquante — CoinGecko free marche sans clé, donc « configured » = endpoint présent). `Actions\RefreshPositionPrices` (collecte les positions `AssetType::Crypto` de tous les users, mappe `asset_symbol`→id CoinGecko, fetch prix cachés, met à jour `current_price` des positions correspondantes). `Console\RefreshMarketPrices` command (`finance:refresh-market-prices`) appelant l'action ; scheduler hourly dans `FinanceServiceProvider`. Config `finance.marketdata.*` (endpoint, api_key, cache_ttl, coingecko_ids map) + `.env.example` COINGECKO_*. Binding `MarketDataManager` dans `FinanceServiceProvider::register()`.
- Tests : SDK `prices()` parse la réponse CoinGecko mockée ; `RefreshPositionPrices` met à jour `current_price` des positions crypto (mappées) et ignore les non-crypto, prix mocké/caché.

**Hors périmètre (reporté) :**
- Cours actions/ETF (autre API keyée) ; aucune nouvelle tuile (la tuile Finance existante bénéficie) ; pas d'IntegrationConnection (clé applicative).

## 4. SDK `Foutraz/SDK_MarketData` (mirror SDK_Weather)

`MarketDataManager(string $endpoint, string $apiKey, ?ClientInterface $client = null)` ; base `https://api.coingecko.com`. `prices()`:
- GET `/api/v3/simple/price` query `{ids: 'bitcoin,ethereum', vs_currencies: 'eur'}`. Si `apiKey` non vide → header `x-cg-demo-api-key`.
- Réponse `{"bitcoin":{"eur":58000},"ethereum":{"eur":3200}}` → normalisée en `array<string id, float price>` (`['bitcoin'=>58000.0, 'ethereum'=>3200.0]`) pour la devise demandée.
- `MakesHttpRequests` copié du SDK_Weather (get + error handling par code HTTP). Pas de DTO obligatoire (retour `array<string,float>`), ou un petit `PriceQuote` si plus propre.

> Endpoints CoinGecko documentés ; validation live à faire. Tests mockés.

## 5. Module finance

- `MarketPriceService(MarketDataManager $manager)` : `pricesFor(array $coingeckoIds, string $vsCurrency = 'eur'): array<string,float>` → `Cache::remember('finance:marketdata:'.implode(',',sorted ids).':'.$vsCurrency, config('finance.marketdata.cache_ttl', 900), fn () => $manager->prices($ids, $vsCurrency))`.
- `RefreshPositionPrices` action : `Position::query()->where('asset_type', AssetType::Crypto)->get()` ; construire la map symbole→id via `config('finance.marketdata.coingecko_ids')` (ex. `['BTC'=>'bitcoin']`) avec fallback `strtolower($symbol)` ; `MarketPriceService::pricesFor(uniqueIds)` ; pour chaque position crypto, si un prix existe pour son id → `forceFill(['current_price'=>$price])->save()`. Idempotent (re-run met à jour la même valeur).
- `RefreshMarketPrices` command (`finance:refresh-market-prices`) → invoque l'action. Scheduler `FinanceServiceProvider::boot()` (console) : `$schedule->command(RefreshMarketPrices::class)->hourly()`.
- Config + binding `MarketDataManager` (mirror WeatherManager binding) ; `.env.example` COINGECKO_API_KEY (optionnel), COINGECKO_ENDPOINT=https://api.coingecko.com.

## 6. Tests (PHPUnit, MockHandler)

- **SDK** : `MarketDataManager` + MockHandler → `prices(['bitcoin','ethereum'],'eur')` renvoie `['bitcoin'=>58000.0,'ethereum'=>3200.0]`.
- **RefreshPositionPrices** : 2 positions crypto (BTC, ETH) + 1 position stock (AAPL) pour un user ; bind `MarketDataManager`/`MarketPriceService` mocké renvoyant des prix ; lancer l'action → `current_price` des positions crypto mis à jour (BTC, ETH), la position stock inchangée. Re-run idempotent. Le mapping symbole→id testé (BTC→bitcoin via config ou fallback).
- Transverse : `pint --dirty`, Larastan niveau 7, `php artisan test --compact`.

## 7. Forme d'exécution

1. SDK `Foutraz/SDK_MarketData` (mirror SDK_Weather) + symlink + composer require + SDK test mocké.
2. finance : `MarketPriceService` (cache) + `RefreshPositionPrices` action + `RefreshMarketPrices` command + scheduler + config + binding + tests.
3. Vérif : pint, Larastan niv. 7, suite complète.

## 8. Critères d'acceptation

- Suite verte sans credentials réels (CoinGecko free / HTTP mocké).
- `RefreshPositionPrices` met à jour `current_price` des positions crypto (mappées CoinGecko), ignore les non-crypto ; re-run idempotent ; cours cachés.
- La tuile/page Finance existante reflète les `current_price` rafraîchis (pas de nouvelle tuile).
- `pint` propre, Larastan niveau 7 sans nouvelle erreur.

# Phase 3.3 — Intégration MarketData Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Rafraîchir le cours des positions crypto (CoinGecko) via un job planifié caché qui met à jour `Position.current_price`, rendant la tuile Finance existante vivante — clé API seule, testé sans credentials réels.

**Architecture:** SDK `foutraz/market-data` mirror du SDK_Weather (key-only). `MarketPriceService` (cache `Cache::remember`, mirror WeatherForecastService) + `RefreshPositionPrices` action + `RefreshMarketPrices` command + scheduler dans `functional/finance`. Aucun nouveau modèle/connexion/controller/tuile.

**Tech Stack:** PHP 8.4, Laravel v13, Guzzle 7, OSDD, PHPUnit 12, Larastan, Pint.

## Global Constraints

- Pas de try/catch (app code ; SDK peut lever des exceptions de domaine comme SDK_Weather) ; pas de commentaires (docstrings PHPDoc anglais, une phrase) ; modèles fins.
- Constructor property promotion ; types explicites ; accolades.
- Larastan niveau 7 sans nouvelle erreur (`--memory-limit=512M`, scope incluant `database/` si factory touchée) ; `vendor/bin/pint --dirty --format agent` avant finalisation.
- Tests PHPUnit class-based, `#[Test]`, `RefreshDatabase`, factories, Guzzle `MockHandler` pour tout HTTP. Aucune clé réelle requise (CoinGecko free / mock).
- Branche `feature/phase-3-3-marketdata` (sur 3.2) ; commit gitmoji d'une phrase ; push après chaque commit ; jamais de push develop/main.
- SDK hors repo dashboard (`../SDK_MarketData`, package `foutraz/market-data`, path repo symlink:false), symliné dans le worktree, livrable séparé.
- Pas de nouvelle tuile → `DashboardStatsTest` reste à 10 (ne pas y toucher).

## Méthode « mirror »
Read the named templates (SDK_Weather, functional/moto WeatherForecastService + MotoServiceProvider binding) and reproduce with the CoinGecko deltas.

---

### Task 1: SDK `Foutraz/SDK_MarketData` (`foutraz/market-data`)

Mirror `/home/quentin/LaravelProjects/SDK_Weather` → `/home/quentin/LaravelProjects/SDK_MarketData`, namespace `Foutraz\MarketData\`.

- `MarketDataManager(string $endpoint, string $apiKey, ?ClientInterface $client = null)` (key-only, mirror WeatherManager) + `prices()` action accessor. Base default `https://api.coingecko.com`.
- `Actions/ManagesPrices`: `prices(array $ids, string $vsCurrency = 'eur'): array<string, float>` — GET `/api/v3/simple/price` with query `ids=implode(',',$ids)`, `vs_currencies=$vsCurrency`; if `apiKey` non-empty, send header `x-cg-demo-api-key: {apiKey}`. Parse `{"bitcoin":{"eur":58000}}` → `['bitcoin'=>58000.0]` (read the requested currency, cast to float). Skip ids absent from the response.
- `Concerns/MakesHttpRequests`: copy from SDK_Weather but DROP the OpenWeather-specific `defaultQuery()` (`appid`/`units`) — CoinGecko auth is a header (set in the client) or the optional key header per request; keep generic get + HTTP-code error handling.
- Exceptions: copy from SDK_Weather. Provider mirror WeatherServiceProvider. composer.json mirror SDK_Weather's (illuminate/support `^11.0|^12.0|^13.0`).

- [ ] **Step 1:** Read SDK_Weather fully; create SDK_MarketData mirroring it with the CoinGecko deltas.
- [ ] **Step 2:** `ln -sfn /home/quentin/LaravelProjects/SDK_MarketData /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/SDK_MarketData`; add root composer path repo `{"type":"path","url":"../SDK_MarketData","options":{"symlink":false}}` + `"foutraz/market-data":"*"` to require; `composer require foutraz/market-data:"*" --no-interaction`.
- [ ] **Step 3:** Test `tests/Feature/Finance/MarketDataSdkTest.php` (plain Tests\TestCase, no DB): MockHandler client; `prices(['bitcoin','ethereum'],'eur')` on a mocked `{"bitcoin":{"eur":58000},"ethereum":{"eur":3200}}` → returns `['bitcoin'=>58000.0,'ethereum'=>3200.0]` (assertEqualsWithDelta). PASS.
- [ ] **Step 4:** pint; commit dashboard `✨ pull market data sdk in as a path package` (composer.json + composer.lock + test); push. Commit SDK in its own repo (`cd ../SDK_MarketData && git init -q if needed && git -c user.name=Quentin -c user.email=v.ballah@xefi.mu add -A && commit -m "✨ initial MarketData (CoinGecko) SDK"`).

---

### Task 2: finance — MarketPriceService + RefreshPositionPrices + command + scheduler

**Files (in functional/finance):** `src/Services/MarketPriceService.php`, `src/Actions/RefreshPositionPrices.php`, `src/Console/RefreshMarketPrices.php`, modify `config/finance.php` + `.env.example`, modify `FinanceServiceProvider.php` (bind manager + schedule). Test `tests/Feature/Finance/RefreshPositionPricesTest.php`.

Templates to read: `functional/moto/src/Services/WeatherForecastService.php` (caching pattern), `functional/moto/src/Providers/MotoServiceProvider.php` (manager binding), `functional/recurring-expenses/src/Console/SendDueExpenseReminders.php` + its provider scheduler (command + schedule pattern).

- `MarketPriceService(MarketDataManager $manager)`: `pricesFor(array $coingeckoIds, string $vsCurrency = 'eur'): array<string,float>` → `Cache::remember('finance:marketdata:'.$vsCurrency.':'.implode(',', $sortedIds), (int) config('finance.marketdata.cache_ttl', 900), fn (): array => $this->manager->prices($coingeckoIds, $vsCurrency))`. Return `[]` if `$coingeckoIds` empty (no API call).
- `RefreshPositionPrices` action `__invoke(): int` (returns count updated): load `Position::query()->where('asset_type', AssetType::Crypto)->get()`; build symbol→id map: for each, `$id = config('finance.marketdata.coingecko_ids')[$position->asset_symbol] ?? strtolower($position->asset_symbol)`; gather unique ids; `$prices = $this->priceService->pricesFor($ids, ...)`; for each crypto position whose mapped id is in `$prices`, `forceFill(['current_price' => $prices[$id]])->save()`; return updated count.
- `RefreshMarketPrices` command (`$signature = 'finance:refresh-market-prices'`) → calls the action, outputs the count.
- `FinanceServiceProvider::register()`: bind `MarketDataManager` singleton from `config('finance.marketdata.endpoint'|'api_key')` (mirror the WeatherManager binding shape). `boot()` (console): `callAfterResolving(Schedule::class, fn (Schedule $s) => $s->command(RefreshMarketPrices::class)->hourly())`; and register the command (loadMigrations-style — add to `$this->commands([...])` if the module already does, else register in boot). Read FinanceServiceProvider first to integrate cleanly.
- `config/finance.php` add `'marketdata' => ['endpoint'=>env('COINGECKO_ENDPOINT','https://api.coingecko.com'), 'api_key'=>env('COINGECKO_API_KEY'), 'cache_ttl'=>env('FINANCE_MARKETDATA_CACHE_TTL',900), 'coingecko_ids'=>['BTC'=>'bitcoin','ETH'=>'ethereum']]`. `.env.example` add COINGECKO_API_KEY=, COINGECKO_ENDPOINT=https://api.coingecko.com.

- [ ] **Step 1 (TDD):** `RefreshPositionPricesTest`: create a user + 2 crypto Positions (asset_symbol BTC, ETH; current_price null) + 1 stock Position (AAPL). Bind a fake `MarketPriceService` (or `MarketDataManager` with MockHandler) returning `['bitcoin'=>58000.0,'ethereum'=>3200.0]`. Run `app(RefreshPositionPrices::class)()` → assert BTC position current_price == 58000.0, ETH == 3200.0, AAPL unchanged (null). Re-run → idempotent (same values). RED.
- [ ] **Step 2:** Implement until GREEN. Also add a quick SDK-cache assertion if easy (optional).
- [ ] **Step 3:** pint; `phpstan analyse functional/finance/src --memory-limit=512M`; `php artisan test --compact tests/Feature/Finance`; confirm the command registers (`php artisan list | grep market`). Commit `✨ refresh crypto position prices from market data on a schedule`; push.

---

### Task 3: Intégration — qualité & suite complète

- [ ] `vendor/bin/pint --dirty --format agent` clean; `vendor/bin/phpstan analyse --memory-limit=512M` 0 new errors; `php artisan test --compact` 0 failures (all MarketData/finance tests + every pre-existing test; DashboardStatsTest stays at 10). Final formatting commit if needed; push.

---

## Notes d'exécution
- Mirror the SDK_Weather (key-only) + WeatherForecastService caching + recurring-expenses command/scheduler patterns. No OAuth, no IntegrationConnection, no controller, no new model, no new dashboard tile.
- SDK lives in `../SDK_MarketData` (symlink + separate repo commit); all HTTP mocked in tests.
- CoinGecko uses ids (`bitcoin`) not tickers (`BTC`): map via `config('finance.marketdata.coingecko_ids')` with `strtolower()` fallback.
- Stocks/ETF price refresh is OUT of scope (CoinGecko = crypto); only `AssetType::Crypto` positions are updated.
- Do NOT change `DashboardStatsTest` (no new tile; count stays 10).

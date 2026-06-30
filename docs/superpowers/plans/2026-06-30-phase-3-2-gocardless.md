# Phase 3.2 — Intégration GoCardless Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Brancher GoCardless Bank Account Data (soldes + transactions réels) en mirror de l'intégration Withings/health, avec un flux requisition, testé sans credentials réels.

**Architecture:** Mirror `functional/health` (the freshest in-repo template) + the SDK `../SDK_Withings`. Differences: requisition flow (not OAuth code); app-level token from secret_id/secret_key; bank data lives in `functional/finance`; `IntegrationConnection.meta` holds `requisition_id` + `account_ids`.

**Tech Stack:** PHP 8.4, Laravel v13, Guzzle 7, Livewire, OSDD, PHPUnit 12, Larastan, Pint.

## Global Constraints

- Ids ULID ; pas de try/catch (app code ; le SDK peut lever des exceptions de domaine comme le template) ; pas de commentaires (docstrings PHPDoc anglais, une phrase) ; modèles fins.
- Constructor property promotion ; types explicites ; accolades ; DTO `final readonly`.
- Jobs idempotents : `WithoutOverlapping($key)`, `public int $tries=3`, `public array $backoff=[60,300,900]`, upsert sur clé externe unique.
- `IntegrationConnection` réutilisé (provider cast, access/refresh encrypted, expires_at, meta json) ; `RefreshesAccessToken` + `ConnectionTokenResolver` réutilisés.
- Larastan niveau 7 sans nouvelle erreur (`--memory-limit=512M`) ; `vendor/bin/pint --dirty --format agent` avant finalisation.
- Tests PHPUnit class-based, `#[Test]`, `RefreshDatabase`, factories, Guzzle `MockHandler` pour tout HTTP. Aucune clé réelle requise.
- Branche `feature/phase-3-2-gocardless` (sur 3.1) ; commit gitmoji d'une phrase ; push après chaque commit ; jamais de push develop/main.
- SDK hors repo dashboard (`../SDK_GoCardlessBank`, package `foutraz/gocardless-bank`), symliné dans le worktree, livrable séparé.

## Méthode « mirror »
Each task names the template file(s) to READ (functional/health/* or /home/quentin/LaravelProjects/SDK_Withings/*) and the GoCardless deltas. Reproduce faithfully; don't improvise a different structure.

---

### Task 1: SDK `Foutraz/SDK_GoCardlessBank` (`foutraz/gocardless-bank`)

Mirror `/home/quentin/LaravelProjects/SDK_Withings` → `/home/quentin/LaravelProjects/SDK_GoCardlessBank`, namespace `Foutraz\GoCardlessBank\`.

**Manager:** `GoCardlessManager(string $endpoint, string $secretId, string $secretKey, string $redirectUri, ?ClientInterface $client = null)` + `setToken()`, `auth()`, `requisitions()`, `accounts()`. Base `https://bankaccountdata.gocardless.com`.
**Actions:**
- `ManagesAuthentication`: `newToken(): TokenResponse` (POST `/api/v2/token/new/` `{secret_id, secret_key}` → `{access, access_expires, refresh, refresh_expires}`; `accessToken=access`, `refreshToken=refresh`, `expiresAt = time()+access_expires`); `refreshToken(string $refresh): TokenResponse` (POST `/api/v2/token/refresh/` `{refresh}` → `{access, access_expires}`).
- `ManagesRequisitions`: `createAgreement(array $opts = []): array` (POST `/api/v2/agreements/enduser/`); `createRequisition(string $institutionId, string $redirect, ?string $agreementId = null): Requisition` (POST `/api/v2/requisitions/` `{institution_id, redirect, agreement?}` → `Requisition{id, link, status, accounts}`); `get(string $id): Requisition` (GET `/api/v2/requisitions/{id}/`).
- `ManagesAccounts`: `balances(string $accountId): array<BankBalance>` (GET `/api/v2/accounts/{id}/balances/` → `body.balances[]{balanceAmount:{amount,currency}, balanceType}`); `transactions(string $accountId, ?string $from = null, ?string $to = null): array<BankTransaction>` (GET `/api/v2/accounts/{id}/transactions/` → `body.transactions.booked[]{transactionId, bookingDate, transactionAmount:{amount,currency}, remittanceInformationUnstructured, creditorName/debtorName}`); `details(string $accountId): array` (GET `/api/v2/accounts/{id}/details/` → `body.account{iban, name, currency}`).
**DTOs (`final readonly`):** `TokenResponse` (accessToken, refreshToken, expiresAt:int), `Requisition` (id, link, status, accounts:array), `BankBalance` (amount:float, currency:string, type:string), `BankTransaction` (externalId, amount:float, currency, bookedAt:DateTimeImmutable, description, counterparty). Auth = Bearer for all non-token calls; token POSTs use JSON body. Copy `MakesHttpRequests` + exceptions from SDK_Withings; non-2xx → domain exception (GoCardless uses HTTP status codes, not an in-body status envelope — so the generic Strava/Withings HTTP-code error handling applies; no in-body status guard needed).

- [ ] **Step 1:** Read SDK_Withings fully; create SDK_GoCardlessBank mirroring it with the GoCardless deltas above.
- [ ] **Step 2:** `ln -sfn /home/quentin/LaravelProjects/SDK_GoCardlessBank /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/SDK_GoCardlessBank`; add root composer path repo `{"type":"path","url":"../SDK_GoCardlessBank"}` + `"foutraz/gocardless-bank":"*"` to require; `composer require foutraz/gocardless-bank:"*" --no-interaction`.
- [ ] **Step 3:** Test `tests/Feature/Finance/GoCardlessSdkTest.php` (mirror WithingsSdkTest): MockHandler client; assert `auth()->newToken()` parses token; `requisitions()->createRequisition(...)` parses `{id, link, status}`; `accounts()->transactions('acc')` parses one booked transaction (amount float). PASS.
- [ ] **Step 4:** pint; commit `✨ pull gocardless bank sdk in as a path package` (composer.json + composer.lock + test); push. Commit the SDK in its own repo: `cd ../SDK_GoCardlessBank && git init -q (if needed) && git -c user.name=Quentin -c user.email=v.ballah@xefi.mu add -A && git -c user.name=Quentin -c user.email=v.ballah@xefi.mu commit -m "✨ initial GoCardless Bank Account Data SDK"`.

---

### Task 2: `IntegrationProvider::GoCardless`

- [ ] Add `case GoCardless = 'gocardless';` + `self::GoCardless => 'GoCardless',` to `label()` (and every match) in `technical/integrations/src/Enums/IntegrationProvider.php`. `phpstan` clean. pint. Commit `✨ register the gocardless integration provider`; push.

---

### Task 3: finance data layer — BankAccount + BankTransaction

**Files:** in `functional/finance`: `src/Models/BankAccount.php`, `src/Models/BankTransaction.php`, migrations, factories, `src/Actions/UpsertBankAccount.php`, `src/Actions/UpsertBankTransaction.php`. Test `tests/Feature/Finance/BankSyncUpsertTest.php`.

Mirror the `functional/health` `BodyMeasurement` model+migration+factory+`UpsertBodyMeasurement` shape.
- `BankAccount` (ULID): `integration_connection_id`, `user_id`, `external_id` (GoCardless account id), `institution_id`, `name`, `iban`, `currency`, `balance` (decimal:2), `balance_at` (datetime), `raw` (json). Unique `[integration_connection_id, external_id]`. Relations `connection()`, `user()`, `transactions()` (hasMany BankTransaction).
- `BankTransaction` (ULID): `bank_account_id`, `user_id`, `external_id` (transactionId), `amount` (decimal:2), `currency`, `booked_at` (datetime), `description`, `counterparty`, `raw` (json). Unique `[bank_account_id, external_id]`. Relations `account()`, `user()`.
- `UpsertBankAccount`: `updateOrCreate(['integration_connection_id','external_id'],[...])`. `UpsertBankTransaction`: `updateOrCreate(['bank_account_id','external_id'],[...])`.

- [ ] TDD: `BankSyncUpsertTest` — upsert the same account twice → 1 row (balance updated); upsert the same transaction twice → 1 row. RED→GREEN. pint+phpstan. Commit `✨ add bank account and transaction data layer to finance`; push.

---

### Task 4: token refresher + manager builder + connection finder + sync jobs

Mirror `functional/health/src/Services/WithingsTokenRefresher.php`, `Actions/BuildUserWithingsManager.php`, `Actions/FindOrCreateWithingsConnection.php`, `Jobs/SyncWithingsUserJob.php` + `SyncWithingsMeasurementsJob.php` → GoCardless equivalents in `functional/finance/src/`:
- `Services/GoCardlessTokenRefresher` (via `auth()->refreshToken()`).
- `Actions/BuildUserGoCardlessManager` (ConnectionTokenResolver + new GoCardlessTokenRefresher).
- `Actions/FindOrCreateGoCardlessConnection(string $userId, TokenResponse $token, array $meta): IntegrationConnection` (provider GoCardless; access/refresh/expires from token; `meta` = {requisition_id, account_ids, institution_id}).
- `Jobs/SyncBankAccountsJob(string $connectionId)`: `tries=3`, backoff, `WithoutOverlapping($connectionId)`; `handle(BuildUserGoCardlessManager, UpsertBankAccount)`: find connection; for each `account_id` in `meta['account_ids']`, fetch `accounts()->details()` + `balances()`, `UpsertBankAccount`, then `SyncBankTransactionsJob::dispatch($bankAccount->id)`.
- `Jobs/SyncBankTransactionsJob(string $bankAccountId)`: `tries=3`, backoff, `WithoutOverlapping($bankAccountId)`; `handle(BuildUserGoCardlessManager, UpsertBankTransaction)`: find BankAccount + its connection; fetch `accounts()->transactions($externalId)`, upsert each.

- [ ] TDD: `GoCardlessTokenRefresherTest` (mocked refresh → shape). `SyncBankAccountsJobTest` (mock manager returning 1 account with balance + 2 booked transactions; dispatch the accounts job TWICE → 1 BankAccount + 2 BankTransaction rows, no duplicates, scoped to user; mirror the Withings sync job test's manager-stub pattern). RED→GREEN. pint+phpstan. Commit `✨ add gocardless token refresh and idempotent bank sync jobs`; push.

---

### Task 5: GoCardlessConnectionController + routes + config + binding + scheduler

Mirror `functional/health/src/Http/Controllers/WithingsConnectionController.php` → `functional/finance/src/Http/Controllers/GoCardlessConnectionController.php`, adapted to the requisition flow:
- `connect()`: `$manager->setToken($manager->auth()->newToken()->accessToken)`; `createAgreement()`; `createRequisition(config('finance.gocardless.institution_id'), route('finance.gocardless.callback'), $agreementId)`; store the requisition id in session (`gocardless_requisition`); `redirect()->away($requisition->link)`.
- `callback(FindOrCreateGoCardlessConnection $finder)`: read the returned requisition ref (`$request->query('ref')` or the GoCardless `ref`); validate it `hash_equals` against the session `gocardless_requisition`; `$manager` re-`newToken()`; `$requisition = $manager->requisitions()->get($ref)`; if not linked → throw `GoCardlessCallbackDeniedException`; `$finder((string) Auth::id(), $token, ['requisition_id'=>$requisition->id, 'account_ids'=>$requisition->accounts, 'institution_id'=>config(...institution)])`; `SyncBankAccountsJob::dispatch($connection->id)`; redirect `route('finance')`.
- `syncNow()`: find user's GoCardless connection (else `GoCardlessNotConnectedException`); dispatch `SyncBankAccountsJob`.
- Exceptions `GoCardlessCallbackDeniedException`, `GoCardlessNotConnectedException` (mirror the Withings ones).
- Routes in `functional/finance/routes/web.php`: `finance.gocardless.connect|callback|sync` (`web`+`auth:web`).
- Config `functional/finance/config/finance.php` add `gocardless` block; `.env.example` GOCARDLESS_SECRET_ID, GOCARDLESS_SECRET_KEY, GOCARDLESS_REDIRECT_URI="${APP_URL}/finance/gocardless/callback", GOCARDLESS_ENDPOINT=https://bankaccountdata.gocardless.com, GOCARDLESS_INSTITUTION_ID=SANDBOXFINANCE_SFIN0000.
- Bind `GoCardlessManager` in `FinanceServiceProvider::register()` from config. Scheduler in boot (console): daily dispatch `SyncBankAccountsJob` per GoCardless connection.

- [ ] TDD: `GoCardlessCallbackTest` (mirror WithingsCallbackTest): `Queue::fake()`; bind `GoCardlessManager` with MockHandler returning newToken then requisition (status linked, accounts:["acc-1"]); session `gocardless_requisition`='req-1'; GET callback `?ref=req-1` → IntegrationConnection (provider GoCardless, meta.account_ids=["acc-1"]) created + `Queue::assertPushed(SyncBankAccountsJob)`; redirect finance. Mismatched ref rejected. RED→GREEN. pint+phpstan. Commit `✨ add gocardless requisition connection controller and scheduler`; push.

---

### Task 6: BankDashboardSummary tile (order 100)

Mirror `functional/health/src/Dashboard/HealthDashboardContribution.php` → `functional/finance/src/Dashboard/BankDashboardSummary.php` implementing `ProvidesDashboardSummary` + `ProvidesNavigationItem`:
- key `bank`, title `Banque`, accent (pick), icon (bank/wallet SVG), href `route('finance')`, order 100.
- `available` = a GoCardless `IntegrationConnection` exists for the user; metric = sum of the user's `BankAccount.balance` formatted € (e.g. `number_format($sum,0,',',' ')`, unit `€`); secondaryLines = account count; callToAction `Connecter ma banque` when not connected.
- Register both tags in `FinanceServiceProvider::register()`.

- [ ] TDD: `BankDashboardSummaryTest` (mirror HealthDashboardContributionTest): GoCardless connection + 2 BankAccounts (balances 100 + 250) for the user → key `bank`, available true, metric `350` €, scoped (other user's account ignored); no connection → available false + CTA. RED→GREEN.
- [ ] Update `tests/Feature/Dashboard/DashboardStatsTest.php` summary count 9→10 (legitimate: bank is the 10th tile). Run `php artisan test --compact tests/Feature/Finance tests/Feature/Dashboard` — all green. pint+phpstan. Commit `✨ surface the real bank balance tile on the dashboard`; push.

---

### Task 7: Intégration — qualité & suite complète

- [ ] `vendor/bin/pint --dirty --format agent` clean; `vendor/bin/phpstan analyse --memory-limit=512M` 0 new errors; `php artisan test --compact` 0 failures (all GoCardless/bank tests + every pre-existing test). Final formatting commit if needed; push.

---

## Notes d'exécution
- Mirror discipline: read `functional/health` + `../SDK_Withings` templates; reproduce with the GoCardless deltas. The requisition flow is the main structural delta vs Withings (connect creates a requisition + redirects to the bank link; callback polls the requisition for account_ids — no `code` exchange).
- GoCardless uses HTTP status codes for errors (unlike Withings' in-body `status` envelope), so the generic SDK HTTP-error handling (copied from SDK_Withings/Strava) is correct as-is; no in-body status guard.
- SDK lives in `../SDK_GoCardlessBank` (symlink + separate repo commit); all HTTP mocked in tests.
- New SDK + (no new module — finance is extended) wiring: add `foutraz/gocardless-bank` to root composer require + `composer require`.
- DashboardStatsTest count rises 9→10 (bank tile).

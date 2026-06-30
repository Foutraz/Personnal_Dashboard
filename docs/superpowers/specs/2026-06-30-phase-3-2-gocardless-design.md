# Phase 3.2 — Intégration GoCardless Bank Account Data (agrégation bancaire)

> Design auto-validé le 2026-06-30 (mode autonome). Deuxième intégration de la Phase 3.
> Branche `feature/phase-3-2-gocardless`, **empilée sur** `feature/phase-3-1-withings`.

## 1. Contexte & principe

Agrégation bancaire (GoCardless Bank Account Data, ex-Nordigen) : soldes réels + transactions. Bâtie en **mirror de l'intégration Withings/health** (le template fraîchement éprouvé), avec une différence clé : le flux de connexion est une **requisition** (l'utilisateur est redirigé vers sa banque, consent, revient) et non un échange de code OAuth. Le token d'accès est **niveau application** (secret_id/secret_key), la requisition est **par utilisateur**.

Construit et **testé sans credentials réels** (HTTP entièrement mocké). L'utilisateur ajoute les clés `.env` et valide en live ensuite.

## 2. Décisions de scope (mode autonome)

| # | Décision | Raison |
|---|----------|--------|
| P32-D1 | SDK `Foutraz/SDK_GoCardlessBank` (`foutraz/gocardless-bank`), package `path` sibling + symlink | Même mécanisme que les SDK existants. |
| P32-D2 | Modèles `BankAccount` + `BankTransaction` dans **`functional/finance`** | Domaine financier ; pas de nouveau module (finance possède déjà l'argent). |
| P32-D3 | Token niveau app **stocké par connexion** (access/refresh/expires) ; `requisition_id` + `account_ids` dans `IntegrationConnection.meta` | Réutilise `RefreshesAccessToken`/`ConnectionTokenResolver` (mirror Withings) ; le flux connect diffère (requisition). |
| P32-D4 | **Détection automatique des dépenses récurrentes : reportée** | Heuristique spéculative, non validable sans données réelles ; cœur d'abord. |
| P32-D5 | Tuile finance/banque dédiée `BankDashboardSummary` (ordre 100) — solde total réel | Réutilise le socle Phase 0 ; ne touche pas la tuile finance existante (ordre 20). |
| P32-D6 | HTTP entièrement mocké en test ; aucune clé réelle requise pour le vert | Verification roadmap. |

## 3. Périmètre

**Dans le périmètre :**
- SDK `Foutraz/SDK_GoCardlessBank` : `GoCardlessManager` (mirror WithingsManager : endpoint, secretId, secretKey, redirectUri, ?Client), `Actions\ManagesAuthentication` (`newToken()`, `refreshToken($refresh)`), `Actions\ManagesRequisitions` (`createAgreement()`, `createRequisition(institutionId, redirect, agreementId)`, `getRequisition($id)`), `Actions\ManagesAccounts` (`balances($accountId)`, `transactions($accountId, ?from, ?to)`, `details($accountId)`), DTOs `TokenResponse`, `Requisition`, `BankBalance`, `BankTransaction`, `Concerns\MakesHttpRequests` (JSON bodies + bearer), exceptions, provider. Tests mockés.
- `IntegrationProvider::GoCardless`.
- `functional/finance` extension : modèles `BankAccount` (ULID ; `integration_connection_id`, `user_id`, `external_id` = GoCardless account id unique, `institution_id`, `name`, `iban`, `currency`, `balance` decimal, `balance_at`) + `BankTransaction` (ULID ; `bank_account_id`, `user_id`, `external_id` = transactionId unique par compte, `amount` decimal, `currency`, `booked_at`, `description`, `counterparty`, `raw`) + migrations + factories + unique external keys.
- `GoCardlessTokenRefresher implements RefreshesAccessToken`, `BuildUserGoCardlessManager`, `FindOrCreateGoCardlessConnection` (stocke token + meta requisition), `GoCardlessConnectionController` (connect → agreement+requisition+redirect ; callback → getRequisition → account_ids → store meta → dispatch sync ; syncNow), routes `/finance/gocardless/{connect,callback,sync}`, config + binding `GoCardlessManager` + scheduler.
- Jobs idempotents : `SyncBankAccountsJob` (par connexion : pour chaque account_id, upsert BankAccount via balances+details, dispatch transactions), `SyncBankTransactionsJob` (par compte : upsert BankTransaction). `WithoutOverlapping`, `tries=3`, `backoff [60,300,900]`, upsert sur clé externe unique.
- `UpsertBankAccount` + `UpsertBankTransaction` actions.
- Tuile `BankDashboardSummary` taggée (ordre 100) : solde total des comptes de l'utilisateur, ou CTA « Connecter ma banque ».

**Hors périmètre (reporté) :**
- Détection auto des dépenses récurrentes ; bascule Échéances déclaratif→réel ; MarketData (3.3).

## 4. SDK `Foutraz/SDK_GoCardlessBank` (mirror Withings SDK)

`GoCardlessManager(string $endpoint, string $secretId, string $secretKey, string $redirectUri, ?ClientInterface $client = null)` + `setToken()`, `auth()`, `requisitions()`, `accounts()`. Base `https://bankaccountdata.gocardless.com`. Endpoints :
- `auth()->newToken()` → POST `/api/v2/token/new/` `{secret_id, secret_key}` → `{access, access_expires, refresh, refresh_expires}` → `TokenResponse` (accessToken, refreshToken, expiresAt = now+access_expires).
- `auth()->refreshToken($refresh)` → POST `/api/v2/token/refresh/` `{refresh}`.
- `requisitions()->createAgreement(array $opts)` → POST `/api/v2/agreements/enduser/`.
- `requisitions()->createRequisition(string $institutionId, string $redirect, ?string $agreementId)` → POST `/api/v2/requisitions/` → `Requisition{id, link, status}`.
- `requisitions()->get(string $id)` → GET `/api/v2/requisitions/{id}/` → `Requisition{id, status, accounts[]}`.
- `accounts()->balances($id)` / `transactions($id, ?from, ?to)` / `details($id)`.

> Endpoints/shapes from the documented GoCardless Bank Account Data v2 API ; flag live-validation pending. Tests mock all of it.

## 5. Module finance extension

- `BankAccount` / `BankTransaction` models + migrations + factories (unique `external_id` scoping). `UpsertBankAccount` (updateOrCreate on `[integration_connection_id, external_id]`), `UpsertBankTransaction` (on `[bank_account_id, external_id]`). Idempotent.
- `GoCardlessTokenRefresher` (mirror WithingsTokenRefresher, via `auth()->refreshToken()`). `BuildUserGoCardlessManager` (ConnectionTokenResolver). `FindOrCreateGoCardlessConnection(string $userId, TokenResponse $token, array $meta)` → IntegrationConnection provider GoCardless, meta = {requisition_id, account_ids, institution_id}.
- `GoCardlessConnectionController`:
  - `connect()`: manager `newToken()`, `createAgreement`, `createRequisition(config institution, redirect, agreement)`, store `requisition_id` + token in session, redirect to `requisition.link`.
  - `callback()`: read `ref`/requisition id, `get($requisitionId)` → if status linked, account_ids ; `FindOrCreateGoCardlessConnection` ; dispatch `SyncBankAccountsJob`; redirect `finance`. Validate the stored requisition id (CSRF-equivalent: compare session requisition id with the returned ref via hash_equals).
  - `syncNow()`: find user's GoCardless connection → dispatch `SyncBankAccountsJob`.
- Jobs as in §3. Config `finance.gocardless.*` + `.env.example` GOCARDLESS_* (SECRET_ID, SECRET_KEY, REDIRECT_URI=${APP_URL}/finance/gocardless/callback, ENDPOINT, INSTITUTION_ID default e.g. SANDBOXFINANCE_SFIN0000). Binding `GoCardlessManager` in `FinanceServiceProvider::register()`. Scheduler daily per GoCardless connection.
- `BankDashboardSummary` taggé (order 100, key `bank`, title `Banque`, route `finance`, available = GoCardless connection exists, metric = sum of user's BankAccount balances € or CTA).

## 6. Tests (PHPUnit, MockHandler)

- **SDK**: `auth()->newToken()` parses token; `requisitions()->createRequisition` parses `{id, link, status}`; `requisitions()->get` parses accounts[]; `accounts()->balances/transactions` parse.
- **GoCardlessTokenRefresher**: mocked refresh → `{access_token,refresh_token,expires_at:int}`.
- **Callback**: `Queue::fake()` + manager mocked; valid requisition ref → IntegrationConnection (provider GoCardless, meta account_ids) + dispatch `SyncBankAccountsJob`; requisition-id mismatch rejected.
- **SyncBankAccountsJob / SyncBankTransactionsJob idempotent**: mocked balances+transactions ; 2 runs → no duplicate BankAccount/BankTransaction (unique external keys), scoped to user.
- **BankDashboardSummary**: sum of balances scoped user ; CTA when no connection.
- Transverse : `pint --dirty`, Larastan niveau 7, `php artisan test --compact`.

## 7. Forme d'exécution

1. SDK `Foutraz/SDK_GoCardlessBank` (mirror Withings SDK) + symlink + composer require + SDK test.
2. `IntegrationProvider::GoCardless`.
3. finance data layer: `BankAccount` + `BankTransaction` models + migrations + factories + `UpsertBankAccount`/`UpsertBankTransaction` + tests.
4. token refresher + manager builder + connection finder + the two sync jobs (idempotency tests).
5. `GoCardlessConnectionController` (requisition connect/callback/syncNow) + routes + config + binding + scheduler + callback test.
6. `BankDashboardSummary` taggé (order 100) + tile test (DashboardStatsTest count 9→10).
7. Vérif : pint, Larastan niv. 7, suite complète.

## 8. Critères d'acceptation

- Suite verte sans credentials réels.
- Callback requisition (mocké) crée une `IntegrationConnection` provider GoCardless avec account_ids en meta + dispatch le sync.
- `SyncBankAccountsJob`/`SyncBankTransactionsJob` idempotents (2 runs, pas de doublon) sur clés externes uniques.
- Tuile Banque (solde total réel ou CTA) via le socle Phase 0, scopée user.
- `pint` propre, Larastan niveau 7 sans nouvelle erreur.

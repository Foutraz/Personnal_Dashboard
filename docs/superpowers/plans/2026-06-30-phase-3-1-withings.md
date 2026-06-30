# Phase 3.1 — Intégration Withings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Brancher Withings (santé / mesures corporelles) en mirror exact de l'intégration Strava : SDK `foutraz/withings`, module `functional/health`, OAuth + sync idempotente + tuile dashboard, testé sans credentials réels.

**Architecture:** Mirror du pipeline Strava. Template de référence à LIRE et reproduire : SDK à `/home/quentin/LaravelProjects/SDK_Strava/`, intégration à `functional/sport/` + `technical/integrations/`. On échange endpoints/noms/payloads pour Withings ; tout le HTTP est mocké en test.

**Tech Stack:** PHP 8.4, Laravel v13, Guzzle 7, Livewire, OSDD, PHPUnit 12, Larastan, Pint.

## Global Constraints

- Ids ULID ; pas de `try/catch` ; pas de commentaires (docstrings PHPDoc anglais, une phrase) ; modèles fins.
- Constructor property promotion ; types explicites ; accolades obligatoires ; DTO `readonly` où applicable.
- Jobs : `WithoutOverlapping($connectionId)`, `public int $tries = 3`, `public array $backoff = [60, 300, 900]`, upsert idempotent sur clé externe unique.
- `IntegrationConnection` réutilisé (provider cast enum, access/refresh_token encrypted, expires_at) ; `RefreshesAccessToken` + `ConnectionTokenResolver` réutilisés.
- Larastan niveau 7 sans nouvelle erreur (`--memory-limit=512M`) ; `vendor/bin/pint --dirty --format agent` avant finalisation.
- Tests PHPUnit class-based, `#[Test]`, `RefreshDatabase`, factories, Guzzle `MockHandler` pour tout HTTP. Aucune clé réelle requise pour le vert.
- Branche `feature/phase-3-1-withings` (sur Phase 2) ; commit gitmoji d'une phrase ; `git push` après chaque commit ; jamais de push develop/main.
- Le SDK vit hors du repo dashboard (`../SDK_Withings`, package path `foutraz/withings`) — livrable séparé, symliné dans le worktree.

## Méthode « mirror »

Chaque tâche nomme le **fichier template Strava à lire** et le **fichier cible Withings** + les **deltas exacts**. L'implémenteur LIT le template réel et le reproduit fidèlement avec les deltas. Ne pas inventer une structure différente de celle de Strava.

---

### Task 1: SDK `Foutraz/SDK_Withings` (`foutraz/withings`)

**Files (new repo dir, OUTSIDE the dashboard):** `/home/quentin/LaravelProjects/SDK_Withings/...` mirroring `/home/quentin/LaravelProjects/SDK_Strava/` :
- `composer.json` (name `foutraz/withings`, namespace `Foutraz\Withings\`, guzzle dep — mirror SDK_Strava/composer.json).
- `src/WithingsManager.php` (mirror `StravaManager`: same constructor + `setToken()`, `auth()`, `measurements()`).
- `src/Concerns/MakesHttpRequests.php` (copy verbatim from SDK_Strava — generic HTTP trait).
- `src/Actions/ManagesAuthentication.php` (mirror Strava's: `authorizeUrl(array $scopes, string $state)`, `exchangeToken(string $code): TokenResponse`, `refreshToken(string $refreshToken): TokenResponse`). Withings deltas below.
- `src/Actions/ManagesMeasurements.php` (mirror `ManagesActivities`: `getmeas(int $userid, ?int $lastUpdate = null): array<Measurement>` and/or an `iterate`).
- `src/Dto/TokenResponse.php` (mirror Strava's `fromArray`, reading Withings `body`).
- `src/Dto/Measurement.php` + `src/Dto/MeasureGroup.php` (normalize a Withings measure).
- `src/Exceptions/*` (copy from SDK_Strava).
- `src/Providers/WithingsServiceProvider.php` (mirror StravaServiceProvider).
- Test inside the SDK if SDK_Strava has tests; otherwise the dashboard-side tests cover it.

**Withings deltas (vs Strava):**
- authorizeUrl base: `https://account.withings.com/oauth2_user/authorize2` ; params `response_type=code`, `client_id`, `scope` (default `['user.metrics']`), `redirect_uri`, `state`.
- token endpoint: POST `https://wbsapi.withings.net/v2/oauth2` with `action=requesttoken`, `client_id`, `client_secret`, `grant_type` (`authorization_code` with `code`+`redirect_uri`, or `refresh_token` with `refresh_token`).
- Withings wraps payloads in `{status, body:{...}}`. `TokenResponse::fromArray($data)` reads `$data['body']`: `access_token`, `refresh_token`, `expires_in`, `token_type`, `userid`, `scope`. Compute `expiresAt = time() + expires_in` if not provided.
- getmeas: POST/GET `https://wbsapi.withings.net/measure` `action=getmeas` (+ `meastypes`, `lastupdate`). Response `body.measuregrps[]`: each has `grpid`, `date` (unix), `measures[]` with `type`,`value`,`unit`. Real value = `value * 10**unit`. `Measurement::fromArray` produces `{externalId:grpid, type:int, value:float, unit:string, measuredAt:DateTimeImmutable}` per measure (or per group with a measures array — mirror however cleanly maps to one BodyMeasurement row per measure; prefer one row per (grpid,type)).

- [ ] **Step 1: Mirror the SDK**

Read every file under `/home/quentin/LaravelProjects/SDK_Strava/` and create the parallel file under `/home/quentin/LaravelProjects/SDK_Withings/` with the namespace `Foutraz\Withings\` and the Withings deltas above. Copy `MakesHttpRequests` and `Exceptions/*` verbatim (generic). Keep the manager constructor signature identical.

- [ ] **Step 2: Symlink + require the package**

```bash
ln -sfn /home/quentin/LaravelProjects/SDK_Withings /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/SDK_Withings
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/feature-phase-3-1-withings
```
Add `"foutraz/withings": "*"` to the root `composer.json` `require` (next to `foutraz/strava`), add the path repo `{"type":"path","url":"../SDK_Withings"}` to the root `repositories` if a glob doesn't already cover the siblings (the existing SDKs use explicit path entries — mirror `../SDK_Strava`'s entry), then `composer require foutraz/withings:"*" --no-interaction`.

- [ ] **Step 3: SDK tests (mocked) + verify**

Write a dashboard-side test `tests/Feature/Health/WithingsSdkTest.php` (or an SDK test if SDK_Strava ships tests) that builds `WithingsManager` with a `MockHandler`-backed Guzzle client and asserts: `auth()->exchangeToken('code')` parses a mocked `{status:0, body:{access_token,refresh_token,expires_in,userid}}` into a `TokenResponse`; `measurements()->getmeas(...)` parses a mocked `{status:0, body:{measuregrps:[...]}}` into `Measurement[]` with the `value*10^unit` math.
Run: `php artisan test --compact tests/Feature/Health/WithingsSdkTest.php` → PASS.
`vendor/bin/pint --dirty --format agent`.

- [ ] **Step 4: Commit + push**

```bash
git add composer.json composer.lock tests/Feature/Health/WithingsSdkTest.php
git commit -m "✨ pull withings sdk in as a path package"
git push
```
(The SDK files live in `../SDK_Withings`, outside this repo — note in the report that the SDK is a separate deliverable.)

---

### Task 2: `IntegrationProvider::Withings`

**Files:** Modify `technical/integrations/src/Enums/IntegrationProvider.php`.

- [ ] **Step 1:** Add `case Withings = 'withings';` and the `label()` arm `self::Withings => 'Withings',`. (Add an arm to EVERY `match($this)` in the enum — `UnhandledMatchError` otherwise; currently only `label()`.)
- [ ] **Step 2:** `vendor/bin/phpstan analyse technical/integrations/src --memory-limit=512M` → clean. No dedicated test (covered downstream).
- [ ] **Step 3:** `vendor/bin/pint --dirty --format agent` ; commit `✨ register the withings integration provider` ; push.

---

### Task 3: Module `functional/health` skeleton + data layer

**Files:**
- Create: `functional/health/composer.json` (mirror `functional/sport/composer.json`: name `functional/health`, namespace `Functional\Health\`, provider `Functional\Health\Providers\HealthServiceProvider`).
- Create: `functional/health/src/Providers/HealthServiceProvider.php` (extends `OsddServiceProvider`; boot: loadRoutes, loadViews 'health', loadMigrations+seeders in console).
- Create: `functional/health/routes/web.php` (the `health` page route — placeholder Livewire or a simple view for now; the real page is Task 6).
- Modify root `composer.json` require (+ `"functional/health": "*"`), then `composer require functional/health:"*" --no-interaction`.
- Create: `functional/health/src/Enums/MeasurementType.php` (`Weight='weight'`, `FatRatio='fat_ratio'`, `HeartRate='heart_rate'`, `Other='other'`; `fromWithings(int $type): self` mapping 1→Weight, 6→FatRatio, 11→HeartRate, default Other; `label()`).
- Create: `functional/health/src/Models/BodyMeasurement.php` (ULID; fillable `integration_connection_id,user_id,external_id,type,value,unit,measured_at,raw`; casts `type`=MeasurementType, `value`=float, `measured_at`=datetime, `raw`=array; `connection()`/`user()` BelongsTo).
- Create: migration `functional/health/database/migrations/..._create_body_measurements_table.php` (ulid id, foreignUlid user_id + integration_connection_id, string external_id, string type, float value, string unit nullable, timestamp measured_at, json raw nullable, timestamps; `unique(['integration_connection_id','external_id','type'])`).
- Create: factory `functional/health/database/Factories/BodyMeasurementFactory.php`.
- Create: `functional/health/src/Actions/UpsertBodyMeasurement.php` (mirror `UpsertStravaActivity`: `updateOrCreate(['integration_connection_id'=>..,'external_id'=>..,'type'=>..],[...])`).
- Test: `tests/Feature/Health/BodyMeasurementUpsertTest.php`.

- [ ] **Step 1:** Read `functional/sport/composer.json` + `SportServiceProvider` + `SportActivity` model/migration/factory + `UpsertStravaActivity` as templates. Create the health package skeleton + data layer mirroring them with the deltas above. Add root require + `composer require functional/health:"*" --no-interaction`.
- [ ] **Step 2 (TDD):** Write `BodyMeasurementUpsertTest` (RED): seeding the same `(connection, external_id, type)` twice via `UpsertBodyMeasurement` yields ONE row (idempotent), value updated. Run → RED (class/table missing).
- [ ] **Step 3:** Implement until GREEN: `php artisan test --compact tests/Feature/Health/BodyMeasurementUpsertTest.php`.
- [ ] **Step 4:** pint + `phpstan analyse functional/health/src --memory-limit=512M` ; commit `✨ add health module body measurement data layer` ; push.

---

### Task 4: OAuth + sync (refresher, manager builder, jobs)

**Files:**
- Create: `functional/health/src/Services/WithingsTokenRefresher.php` (mirror `functional/sport/src/Services/StravaTokenRefresher.php`: `refresh(string $refreshToken): array{access_token,refresh_token,expires_at}` via `manager->auth()->refreshToken()`; `expires_at` = `now()->addSeconds($token->expiresIn)->timestamp` if Withings returns expires_in).
- Create: `functional/health/src/Actions/BuildUserWithingsManager.php` (mirror `BuildUserStravaManager`).
- Create: `functional/health/src/Actions/FindOrCreateWithingsConnection.php` (mirror `FindOrCreateConnection`: provider `IntegrationProvider::Withings`, external_id from token `userid`).
- Create: `functional/health/src/Jobs/SyncWithingsUserJob.php` (mirror `SyncStravaAthleteJob`: store external_id/meta, dispatch measurements job).
- Create: `functional/health/src/Jobs/SyncWithingsMeasurementsJob.php` (mirror `SyncStravaActivitiesJob`: `tries=3`, `backoff=[60,300,900]`, `WithoutOverlapping($connectionId)`, iterate getmeas → `UpsertBodyMeasurement`).
- Test: `tests/Feature/Health/SyncWithingsMeasurementsJobTest.php`, `tests/Feature/Health/WithingsTokenRefresherTest.php`.

- [ ] **Step 1:** Read the Strava templates (`StravaTokenRefresher`, `BuildUserStravaManager`, `FindOrCreateConnection`, `SyncStravaAthleteJob`, `SyncStravaActivitiesJob`) and mirror them for Withings.
- [ ] **Step 2 (TDD):** `WithingsTokenRefresherTest` — mocked refresh response → `{access_token,refresh_token,expires_at:int}`. `SyncWithingsMeasurementsJobTest` — bind a fake `BuildUserWithingsManager`/`WithingsManager` returning 3 measure groups (mirror the Strava sync job test's `bindManagerReturning` MockHandler pattern); dispatch the job twice → assert exactly 3 `BodyMeasurement` rows (idempotent), scoped to the connection's user.
- [ ] **Step 3:** Implement until GREEN. Run both tests.
- [ ] **Step 4:** pint + phpstan ; commit `✨ add withings token refresh and idempotent measurement sync` ; push.

---

### Task 5: Connection controller + routes + config + binding + scheduler

**Files:**
- Create: `functional/health/src/Http/Controllers/WithingsConnectionController.php` (mirror `StravaConnectionController`: connect with CSRF `withings_state`, callback validating state + exchanging code + `FindOrCreateWithingsConnection` + dispatch `SyncWithingsUserJob`, syncNow). Exceptions mirror Strava's (`WithingsCallbackDeniedException`, `WithingsNotConnectedException`).
- Modify: `functional/health/routes/web.php` — `health` page + `/health/withings/{connect,callback,sync}` named routes (mirror sport's route names: `health.withings.connect|callback|sync`), `web`+`auth:web`.
- Create: `functional/health/config/health.php` + add `WITHINGS_*` to root `.env.example`.
- Modify: `HealthServiceProvider::register()` — bind `WithingsManager` from config (mirror sport's StravaManager bind); `boot()` — scheduler entry (a command or `SyncWithingsMeasurementsJob` dispatch) periodic, mirror sport's schedule registration.
- Test: `tests/Feature/Health/WithingsCallbackTest.php`.

- [ ] **Step 1:** Read `StravaConnectionController`, sport `routes/web.php`, sport config + StravaManager binding + schedule registration; mirror for Withings.
- [ ] **Step 2 (TDD):** `WithingsCallbackTest` (mirror `StravaCallbackTest`): `Queue::fake()`, bind `WithingsManager` with MockHandler returning a token payload; GET the callback with valid state+code → creates `IntegrationConnection` (provider Withings, external_id) + `Queue::assertPushed(SyncWithingsUserJob::class)`; redirects to `health`. RED → implement → GREEN.
- [ ] **Step 3:** pint + phpstan ; commit `✨ add withings connection controller, routes and scheduler` ; push.

---

### Task 6: Health dashboard page + tagged summary tile

**Files:**
- Create: `functional/health/src/Livewire/HealthDashboard.php` + `functional/health/resources/views/health.blade.php` (latest weight, connection state, connect/sync buttons — mirror `SportDashboard` minimally). Register `Livewire::component('health-dashboard', ...)` in HealthServiceProvider; wire the `health` route to it.
- Create: `functional/health/src/Dashboard/HealthDashboardContribution.php` implementing `ProvidesDashboardSummary` + `ProvidesNavigationItem` (mirror a Phase 0 `*DashboardContribution`): metric = latest `BodyMeasurement` of type Weight for the user (kg) or CTA "Connecter Withings" if no Withings `IntegrationConnection`; `available` = connection exists; key `health`, title `Santé`, route `health`, order 90, accent (e.g. cyan). Tag `['dashboard.summaries','dashboard.navigation']` in HealthServiceProvider::register().
- Test: `tests/Feature/Health/HealthDashboardContributionTest.php`.

> **REQUIRED SUB-SKILL au rendu** : `frontend-design:frontend-design` pour la page/tuile santé.

- [ ] **Step 1 (TDD):** `HealthDashboardContributionTest` (mirror a Phase 0 contribution test): seed a Withings connection + a Weight BodyMeasurement for the user → `dashboardSummary($user)` returns key `health`, available true, metric = the weight; without a connection → available false + CTA. RED.
- [ ] **Step 2:** Implement the contribution, the Livewire page + view, register the tags + Livewire component. GREEN.
- [ ] **Step 3:** Run `php artisan test --compact tests/Feature/Health tests/Feature/Dashboard` (the health tile must appear via the Phase 0 collectors without touching Dashboard.php/sidebar). pint + phpstan ; commit `✨ surface the health module on the dashboard` ; push.

---

### Task 7: Intégration — qualité & suite complète

**Files:** none (verification).

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent` → clean.
- [ ] **Step 2:** `vendor/bin/phpstan analyse --memory-limit=512M` → 0 new errors.
- [ ] **Step 3:** `php artisan test --compact` → 0 failures (all Health tests + every pre-existing test green).
- [ ] **Step 4:** Final formatting commit if Pint changed anything; push.

---

## Notes d'exécution

- **Mirror discipline**: each task's implementer must READ the named Strava template file(s) and reproduce them with the listed deltas — do not improvise a different structure. The Strava integration is proven and green; fidelity is the goal.
- **SDK lives outside the repo** (`../SDK_Withings`): symlink it into `.claude/worktrees/SDK_Withings` so `../SDK_Withings` resolves from the worktree; it is a separate deliverable (not in the dashboard PR), like the existing SDKs.
- **New packages wiring** (`foutraz/withings`, `functional/health`): add to root composer.json require + `composer require ... --no-interaction` so providers auto-discover; the first failing test that resolves a health/SDK class is the wiring check.
- **All HTTP mocked**: every test uses Guzzle `MockHandler`; no real `WITHINGS_*` credentials needed for green. Mirror the Strava sync-job test's `bindManagerReturning` helper.
- **Withings payload shapes** (`{status, body:{...}}`, `value*10^unit`, meastype codes) are from the documented v2 API; flag in reports that live validation is pending. Tests pin the mocked shape.

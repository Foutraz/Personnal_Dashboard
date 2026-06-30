# Phase 3.1 — Intégration Withings (santé / mesures corporelles)

> Design auto-validé le 2026-06-30 (mode autonome). Première intégration de la Phase 3 de `ROADMAP_INTERCONNEXIONS.md`.
> Branche `feature/phase-3-1-withings`, **empilée sur** `feature/phase-2-notifications`.

## 1. Contexte & principe

Première nouvelle intégration externe, bâtie en **mirror exact de l'intégration Strava** existante (le template canonique : SDK path-repo `foutraz/*`, `IntegrationConnection`, `RefreshesAccessToken`/`ConnectionTokenResolver`, `BuildUserXManager`, jobs idempotents, `XConnectionController`, scheduler, tuile `*DashboardSummary` de Phase 0). On échange les endpoints Strava pour ceux de **Withings** (OAuth2 + Measure API).

Conformément au roadmap : **bâti et testé sans credentials réels** (HTTP mocké via `MockHandler`). L'utilisateur ajoutera les clés `.env` et validera en live ensuite.

## 2. Décisions de scope (mode autonome)

| # | Décision | Raison |
|---|----------|--------|
| P31-D1 | SDK `Foutraz/SDK_Withings` (`foutraz/withings`), package `path` sibling `../SDK_Withings` + symlink worktree | Même mécanisme que les SDK existants ; livrable séparé du repo dashboard. |
| P31-D2 | Nouveau module `functional/health` | Le roadmap le prévoit (santé ≠ sport) ; modèle `BodyMeasurement`. |
| P31-D3 | **`BodyMeasurement` uniquement** (poids + métriques corporelles) ; **`SleepSummary` reporté** | Poids = métrique headline ; garde le périmètre livrable. |
| P31-D4 | **Pas de GoalMetric poids** en 3.1 | `GoalProgress` modélise « plus = mieux » ; un objectif poids (« moins = mieux ») serait mal modélisé — à traiter proprement plus tard. |
| P31-D5 | Tuile `HealthDashboardSummary` taggée (Phase 0) ; pas de provider d'agenda (la santé n'est pas time-anchored au sens agenda) | Réutilise le socle Phase 0 ; pas de surcharge Phase 1. |
| P31-D6 | HTTP entièrement mocké en test ; aucune clé réelle requise pour le vert | Verification roadmap « suite verte sans credentials réels ». |

## 3. Périmètre

**Dans le périmètre :**
- SDK `Foutraz/SDK_Withings` : `WithingsManager` (mirror `StravaManager`), `Actions\ManagesAuthentication` (authorizeUrl / exchangeToken / refreshToken), `Actions\ManagesMeasurements` (getmeas), `Dto\TokenResponse`, `Dto\Measurement` (+ `MeasureGroup`), `Concerns\MakesHttpRequests`, `Providers\WithingsServiceProvider`, exceptions. Tests SDK mockés.
- `IntegrationProvider::Withings`.
- Module `functional/health` : `WithingsTokenRefresher implements RefreshesAccessToken`, `BuildUserWithingsManager`, `FindOrCreateWithingsConnection`, `SyncWithingsMeasurementsJob` + `SyncWithingsUserJob` (idempotents : `WithoutOverlapping(connectionId)`, `tries=3`, `backoff [60,300,900]`, upsert sur clé externe unique), `UpsertBodyMeasurement`, modèle `BodyMeasurement` (ULID + `external_id` unique par connexion) + migration + factory, `WithingsConnectionController` (connect/callback/syncNow) + routes + config + binding `WithingsManager` + scheduler, `HealthDashboard` Livewire + vue, `HealthDashboardSummary` (taggé `dashboard.summaries`/`dashboard.navigation`).
- Tests mockés : token refresh, callback crée la connexion + dispatch sync, job idempotent (2 runs → pas de doublon), tuile scopée user.

**Hors périmètre (reporté) :**
- `SleepSummary`, GoalMetric poids, GoCardless (3.2), MarketData (3.3).

## 4. SDK `Foutraz/SDK_Withings` (mirror Strava)

`WithingsManager(string $endpoint, string $apiToken, string $clientId, string $clientSecret, string $redirectUri, ?ClientInterface $client = null)` avec `setToken()`, `auth()`, `measurements()`. `MakesHttpRequests` (get/post). Endpoints :
- authorizeUrl → `https://account.withings.com/oauth2_user/authorize2?response_type=code&client_id=…&scope=…&redirect_uri=…&state=…`
- exchangeToken / refreshToken → POST `https://wbsapi.withings.net/v2/oauth2` `action=requesttoken`, `grant_type=authorization_code|refresh_token` ; réponse Withings : `{status, body:{userid, access_token, refresh_token, expires_in, token_type, scope}}`. `TokenResponse::fromArray()` lit `body`.
- getmeas → GET/POST `https://wbsapi.withings.net/measure` `action=getmeas` ; réponse `{status, body:{measuregrps:[{grpid, date, measures:[{type, value, unit}]}], more}}`. `Measurement` normalise une mesure (type, valeur réelle = `value * 10^unit`, mesurée_at). `expires_in` → `expires_at` calculé côté refresher.

> Les codes meastype (1=poids kg, 6=ratio masse grasse, 11=fréquence cardiaque…) et la structure `value*10^unit` viennent de la doc Withings v2 ; à confirmer en live. Tests mockés.

## 5. Module `functional/health`

Mirror du module sport, adapté :
- `WithingsTokenRefresher::refresh($refreshToken): array{access_token,refresh_token,expires_at}` — `manager->auth()->refreshToken()` ; `expires_at = now()+expires_in`.
- `BuildUserWithingsManager(IntegrationConnection): WithingsManager` via `ConnectionTokenResolver(new WithingsTokenRefresher(...))`.
- `SyncWithingsUserJob` : `getmeas` user info (ou exchange déjà fournit `userid`) → stocke `external_id`/`meta` → dispatch `SyncWithingsMeasurementsJob`.
- `SyncWithingsMeasurementsJob` : itère les measuregrps → `UpsertBodyMeasurement` (updateOrCreate sur `[integration_connection_id, external_id=grpid]`). Idempotent.
- `BodyMeasurement` (ULID) : `integration_connection_id`, `user_id`, `external_id` (grpid, unique par connexion), `type` (enum `MeasurementType`: Weight, FatRatio, …), `value` (float), `unit` (string), `measured_at` (datetime), `raw` (json). Migration + factory + unique `[integration_connection_id, external_id]`.
- `WithingsConnectionController` connect/callback/syncNow (mirror Strava, state CSRF, `FindOrCreateWithingsConnection`) + routes `/health/withings/{connect,callback,sync}` + `health` page route.
- Config `functional/health/config/health.php` + `.env.example` `WITHINGS_*` ; binding `WithingsManager` dans `HealthServiceProvider::register()` ; scheduler `Schedule::command(...)` (ou job) périodique.
- `HealthDashboard` Livewire + vue (dernier poids, connexion Withings, bouton connect/sync). `HealthDashboardSummary implements ProvidesDashboardSummary, ProvidesNavigationItem` taggé : métrique = dernier poids (kg) ou CTA « Connecter Withings » si non connecté ; route `health`, ordre 90, accent (libre).

## 6. Tests (PHPUnit, MockHandler)

- **SDK** : `ManagesAuthentication` exchange/refresh parse la réponse Withings mockée → `TokenResponse` ; `ManagesMeasurements::getmeas` parse measuregrps → `Measurement[]`.
- **WithingsTokenRefresher** : réponse mockée → shape `{access_token,refresh_token,expires_at:int}`.
- **Callback** : `Queue::fake()` + manager mocké → crée `IntegrationConnection` (provider Withings, external_id) + dispatch `SyncWithingsUserJob` ; state CSRF validé.
- **SyncWithingsMeasurementsJob idempotent** : manager mocké renvoyant 3 measuregrps ; 2 runs → 3 `BodyMeasurement` (pas 6).
- **HealthDashboardSummary** : dernier poids scopé user ; `available=false`+CTA sans connexion.
- Transverse : `pint --dirty`, Larastan niveau 7, `php artisan test --compact`.

## 7. Forme d'exécution

1. **SDK** (séquentiel, fondation) : créer `../SDK_Withings` (composer.json `foutraz/withings`, manager+actions+DTO+trait+exceptions+provider) + symlink worktree + `composer require foutraz/withings` ; tests SDK mockés.
2. **Enum** : `IntegrationProvider::Withings`.
3. **Module health — couche données** : modèle `BodyMeasurement` + migration + factory + `MeasurementType` enum + `UpsertBodyMeasurement`.
4. **Module health — OAuth/sync** : `WithingsTokenRefresher`, `BuildUserWithingsManager`, `FindOrCreateWithingsConnection`, les 2 jobs (tests idempotence).
5. **Module health — HTTP** : `WithingsConnectionController` + routes + config + binding + scheduler + `HealthServiceProvider` (nouveau package functional, composer require) + callback test.
6. **Dashboard** : `HealthDashboard` Livewire + vue + `HealthDashboardSummary` taggé (apparaît sur l'accueil/sidebar via socle Phase 0).
7. **Vérif** : pint, Larastan niv. 7, suite complète.

## 8. Critères d'acceptation

- `composer require foutraz/withings` + `technical/...`/`functional/health` chargés ; suite verte sans credentials réels.
- Callback Withings (mocké) crée une `IntegrationConnection` provider Withings + dispatch le sync.
- `SyncWithingsMeasurementsJob` est idempotent (2 runs, pas de doublon) sur `[connection, external_id]`.
- La tuile Santé apparaît sur l'accueil via le socle Phase 0 (dernier poids ou CTA), scopée user.
- `pint` propre, Larastan niveau 7 sans nouvelle erreur.

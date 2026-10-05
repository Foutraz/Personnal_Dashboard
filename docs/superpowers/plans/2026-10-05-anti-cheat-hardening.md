# Durcissement anti-triche avant G5 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fermer les failles d'antidatage et de lignes fabriquées avant G5 : bornes plausibles et dates passées dans les modules sources (REST et Livewire), champs Strava et cellules explorées hors de portée du propriétaire, complétion des tâches posée par le serveur, règle d'enregistrement à temps pour les défis, instantané de mesure sur les badges, clôture figée par défi et historique de proposition agrégé en une requête groupée par template.

**Architecture:** Chaque module source porte ses bornes dans une classe `src/Validation/<Modèle>Rules.php` partagée par sa ressource Lomkit et son formulaire Livewire ; la base `Technical\Osdd\Rest\Resources\Resource` fournit `serverManagedFieldRules()` contre le `forceFill` de Lomkit. Le layer Objectifs gagne `GoalMetricAggregator` (agrégats SQL inclusifs pour `GoalProgressCalculator::measure()`, seaux demi-ouverts en `CASE` liés pour une série de périodes) et `GoalMetric::isSelfReported()`. La gamification fige `closes_at` sur chaque défi, mesure l'historique et la progression via `WeeklyMetricMeter` en excluant les lignes déclaratives enregistrées après la clôture de leur semaine, et écrit `measured_value` sur chaque attribution de badge. Spec : `docs/superpowers/specs/2026-10-05-anti-cheat-hardening-design.md`.

**Tech Stack:** Laravel 13 / PHP 8.4, layers OSDD `functional/{moto,finance,todo,sport,exploration,goals,gamification}` + `technical/osdd`, Livewire 3, lomkit/laravel-rest-api + laravel-access-control, PHPUnit 12, Pint, Larastan niveau 7 ; MySQL en production, SQLite en tests.

## Global Constraints

- Worktree `/home/qmari/Projects/perso/.dashboard-worktrees/anti-cheat-hardening`, branche `feature/anti-cheat-hardening` (depuis `develop`) ; merger régulièrement `origin/develop` (jamais de rebase) ; ne jamais toucher aux autres worktrees ni à l'arbre principal.
- Commits séparés d'une phrase en anglais avec gitmoji, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, push après chaque commit.
- Aucun commentaire inline ; docblock d'une phrase en anglais seulement s'il porte ce que la signature ne porte pas (type générique, `@throws`, danger nommé) ; pas de try-catch ; ULID partout ; pas de `cascadeOnDelete` ; exceptions nommées ; aucune chaîne magique ; écouteurs de cycle de vie branchés par nom de classe (`Task::saving(StampTaskCompletion::class)`), jamais d'Observer.
- Validation en notation tableau ; bornes en constantes de la classe `Validation` du module ; la ressource REST et le composant Livewire qui créent la même ligne lisent la même classe.
- Aucune fonction de date SQL (`strftime`, `YEARWEEK`, `CONVERT_TZ`, `DATE_ADD`, `datetime()`) ; bornes converties dans le fuseau applicatif (UTC) avant liaison ; seaux par `CASE` à bornes liées ; colonnes entourées par la grammaire du builder ; requêtes sur les modèles (`Model::query()`, `->toBase()`), `DB::table()` réservé au backfill de migration.
- Aucune dépendance Composer / npm nouvelle.
- Tests existants : seuls ceux listés au §13 de la spec sont adaptés ; tout autre test existant reste vert **sans modification**.
- Tests PHPUnit en classes, `#[Test]`, `it_...`, factories, helper `faker()`, temps figé par `$this->travelTo(Carbon::parse('…', 'UTC'))`. Après chaque tâche : `vendor/bin/pint --dirty --format agent` puis les fichiers de test de la tâche et ceux cités à son Step 4.
- Jalons temporels : « maintenant » des tests de validation = `2026-10-01 10:00:00` UTC ; W40 = `2026-W40`, `starts_at` `2026-09-27 22:00:00`, `ends_at` `2026-10-04 22:00:00`, `closes_at` (48 h) `2026-10-06 22:00:00` ; proposition W40 au `2026-09-28 06:00:00` ; clôtures de l'historique W36 `2026-09-08 22:00:00`, W37 `2026-09-15 22:00:00`, W38 `2026-09-22 22:00:00`, W39 `2026-09-29 22:00:00` (UTC).

---

### Task 1: Sorties moto — dates passées, bornes plausibles, horodatages serveur

**Files:**
- Create: `functional/moto/src/Validation/MotoRideRules.php`
- Modify: `technical/osdd/src/Rest/Resources/Resource.php`, `functional/moto/src/Rest/Resource/MotoRideResource.php` (`rules()`), `functional/moto/src/Livewire/MotoDashboard.php` (`logRide()`), `functional/moto/database/Factories/MotoRideFactory.php`
- Test: `tests/Feature/Moto/MotoRideValidationTest.php` (nouveau), ajouts dans `tests/Feature/Moto/MotoDashboardComponentTest.php`

**Interfaces:**
- Produces: `Resource::serverManagedFieldRules(RestRequest $request): array` (protected, `@return array<string, list<string>>`) → `['prohibited']` pour chacun de `id`, `created_at`, `updated_at` **présent dans `fields($request)`**, docblock du danger « Lomkit force-fills every declared field, so a client could otherwise choose the key or the timestamps. » ; `final class MotoRideRules` avec `MAX_DISTANCE_KM = 2000`, `MIN_DURATION_MINUTES = 1`, `MAX_DURATION_MINUTES = 1440`, `EARLIEST_START = '1970-01-01'` et les méthodes statiques (`@return list<string>`) `startedAt()` = `['date', 'after:1970-01-01', 'before_or_equal:now']`, `distance()` = `['numeric', 'gt:0', 'max:2000']`, `durationInSeconds()` = `['integer', 'min:60', 'max:86400']`, `durationInMinutes()` = `['numeric', 'min:1', 'max:1440']` ; `MotoRideFactory` : `created_at` et `updated_at` = `fn (array $attributes): mixed => $attributes['started_at']`.
- Consumes: rien.

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`, propriétaire authentifié sur `/api/moto-rides/mutate`) —
  - création `started_at` `2026-10-01 10:00:01` → 422 sur `mutate.0.attributes.started_at`, 0 sortie ; `2026-10-01 10:00:00` → 200 ; `1970-01-01 00:00:00` → 422 ;
  - mise à jour d'une sortie propre vers `started_at` `2026-10-02 08:00:00` → 422, `started_at` inchangé ;
  - `distance` `0` → 422 ; `2000` → 200 ; `2000.01` → 422 ;
  - `duration` `59` → 422 ; `60` et `86400` → 200 ; `86401` → 422 ;
  - création avec `created_at` `2026-09-01 08:00:00` → 422 sur `mutate.0.attributes.created_at`, 0 sortie ; création avec `id` → 422 ; mise à jour avec `updated_at` → 422, `updated_at` inchangé ;
  - création valide datée `2026-09-14 08:00:00` → `created_at` stocké `2026-10-01 10:00:00` ;
  - Livewire `logRide` : `rideStartedAt` `2026-10-01T10:01` → `assertHasErrors(['rideStartedAt' => 'before_or_equal'])` ; `rideDistance` `2500` → `max`, `0` → `gt` ; `rideDuration` `1441` → `max`, `0` → `min` ; (`2026-10-01T09:00`, `120`, `90`) → sortie créée, `duration` 5400 ;
  - factory : `MotoRide::factory()->create(['started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC')])->created_at` → `2026-09-02 12:00:00`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Moto/MotoRideValidationTest.php` → FAIL.
- [ ] **Step 3:** implémenter : `MotoRideResource::rules()` = `[...$this->serverManagedFieldRules($request), 'title' => …, 'started_at' => MotoRideRules::startedAt(), 'duration' => MotoRideRules::durationInSeconds(), 'distance' => MotoRideRules::distance(), …]` ; `logRide()` en tableaux (`['required', ...MotoRideRules::startedAt()]`, `['required', ...MotoRideRules::durationInMinutes()]`, `['required', ...MotoRideRules::distance()]`).
- [ ] **Step 4:** PASS, plus `tests/Feature/Moto` entier vert ; commit `🔒 bound moto ride inputs to past dates and plausible values and keep their key and timestamps server-managed`.

### Task 2: Transactions d'investissement — `gt:0`, plafonds, dates passées

**Files:**
- Create: `functional/finance/src/Validation/InvestmentTransactionRules.php`
- Modify: `functional/finance/src/Rest/Resource/InvestmentTransactionResource.php` (`rules()`), `functional/finance/src/Livewire/PortfolioOverview.php` (`recordTransaction()`)
- Test: ajouts dans `tests/Feature/Finance/InvestmentTransactionValidationTest.php` et `tests/Feature/Finance/PortfolioOverviewComponentTest.php`

**Interfaces:**
- Produces: `final class InvestmentTransactionRules` avec `MAX_QUANTITY = 1000000000`, `MAX_UNIT_PRICE = 10000000`, `EARLIEST_EXECUTION = '1970-01-01'` et `quantity()` = `['numeric', 'gt:0', 'max:1000000000']`, `unitPrice()` = `['numeric', 'gt:0', 'max:10000000']`, `executedAt()` = `['date', 'after:1970-01-01', 'before_or_equal:now']`.
- Consumes: `Resource::serverManagedFieldRules()` (Task 1).

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`, position du propriétaire, `/api/investment-transactions/mutate`) —
  - achat `quantity` `0` → 422 sur `mutate.0.attributes.quantity` ; `1000000000` → 200 ; `1000000001` → 422 ;
  - vente `unit_price` `0` → 422 sur `mutate.0.attributes.unit_price` ; `10000000` → 200 ; `10000000.01` → 422 ;
  - `executed_at` `2026-10-01 10:00:01` → 422 ; `2026-10-01 10:00:00` → 200 ; `1970-01-01 00:00:00` → 422 ;
  - achat 1 × `50000` → 200 (plausible, la famille finance reste déclarative) ;
  - `created_at`, `updated_at` ou `id` dans les attributs → 422, aucune ligne créée ou modifiée ;
  - Livewire `recordTransaction` : `txQuantity` `0` → `assertHasErrors(['txQuantity' => 'gt'])` ; `txUnitPrice` `0` → `gt` ; `txQuantity` `1000000001` → `max` ; `txUnitPrice` `10000000.01` → `max` ; (`2`, `150.5`) → transaction enregistrée et quantité de la position recalculée à `2`.
- [ ] **Step 2:** FAIL. **Step 3:** `rules()` = `[...$this->serverManagedFieldRules($request), 'position_id' => …, 'type' => …, 'quantity' => InvestmentTransactionRules::quantity(), 'unit_price' => InvestmentTransactionRules::unitPrice(), 'executed_at' => InvestmentTransactionRules::executedAt(), 'note' => …]` ; composant : `['required', ...InvestmentTransactionRules::quantity()]` et `['required', ...InvestmentTransactionRules::unitPrice()]` (le `min:0.00000001` existant disparaît, `gt:0` le remplace).
- [ ] **Step 4:** PASS, plus `tests/Feature/Finance` entier vert ; commit `🔒 require strictly positive capped quantities and prices on investment transactions dated in the past`.

### Task 3: Tâches — complétion posée par le serveur

**Files:**
- Create: `functional/todo/src/Listeners/StampTaskCompletion.php`
- Modify: `functional/todo/src/Rest/Resource/TaskResource.php` (`rules()`), `functional/todo/src/Providers/TodoServiceProvider.php` (`boot()` : `Task::saving(StampTaskCompletion::class)`)
- Test: `tests/Feature/Todo/TaskCompletionStampTest.php` (nouveau), ajouts dans `tests/Feature/Todo/TaskValidationTest.php`

**Interfaces:**
- Produces: `StampTaskCompletion::handle(Task $task): void` — statut `TaskStatus::Done` et `completed_at` nul → `now()` ; tout autre statut → `completed_at = null` ; un `completed_at` déjà posé sur une tâche `Done` est conservé. `TaskResource::rules()` : `completed_at` → `['prohibited']`, plus `serverManagedFieldRules()`.
- Consumes: `Resource::serverManagedFieldRules()` (Task 1).

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`) —
  - création API `status` `done` → `completed_at` `2026-10-01 10:00:00` ;
  - tâche `pending`, `travelTo(2026-10-02 08:00:00)`, mise à jour API `status` `done` → `completed_at` `2026-10-02 08:00:00` ; `travelTo(2026-10-03 09:00:00)`, renommage → `completed_at` toujours `2026-10-02 08:00:00` ; passage à `pending` → `completed_at` nul ;
  - création ou mise à jour API avec `completed_at` `2026-09-01 08:00:00` → 422 sur `mutate.0.attributes.completed_at`, aucune tâche créée, ligne inchangée ; `created_at` ou `id` → 422 ;
  - côté serveur : `Task::factory()->completed()->create(['completed_at' => Carbon::parse('2026-09-20 18:00:00', 'UTC')])` garde `2026-09-20 18:00:00` ; `Task::factory()->create(['completed_at' => Carbon::parse('2026-09-20 18:00:00', 'UTC')])` (statut `Pending`) → `completed_at` nul ;
  - câblage : `Event::fake()` puis `Event::assertListening('eloquent.saving: '.Task::class, StampTaskCompletion::class)`.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter (pas de closure autour de l'écouteur ; `TodoBoard::toggleComplete` reste inchangé).
- [ ] **Step 4:** PASS, plus `tests/Feature/Todo`, `tests/Unit/Todo`, `tests/Feature/Gamification/XpRulesTest.php`, `BadgeRulesTest.php`, `ProcessUserGamificationJobTest.php`, `UpdateStreaksTest.php` verts ; commit `🔒 stamp task completion on the server and refuse client-set completion dates`.

### Task 4: Sources synchronisées hors de portée du propriétaire (Strava, cellules explorées)

**Files:**
- Modify: `functional/sport/src/Rest/Resource/SportActivityResource.php` (`rules()`), `functional/exploration/src/Rest/Controller/ExploredCellsController.php` (`use RejectsApiCreation`), `functional/exploration/src/Rest/Policies/ExploredCellPolicy.php` (`create`, `update`, `delete` → `false`), `functional/exploration/src/Rest/Resource/ExploredCellResource.php` (suppression de `rules()` et `createRules()`), `functional/exploration/src/Actions/RebuildUserCoverage.php`
- Test: ajouts dans `tests/Feature/Sport/ActivitiesApiScopeTest.php`, `tests/Feature/Lomkit/PreventsApiCreationTest.php`, `tests/Feature/Exploration/RebuildCoverageTest.php` ; dans `tests/Feature/Exploration/ExploredCellsApiScopeTest.php`, `it_allows_the_owner_to_update_their_explored_cell` devient `it_forbids_the_owner_to_update_their_explored_cell`

**Interfaces:**
- Produces: `SportActivityResource::rules()` = `serverManagedFieldRules()` (`id`) + `['prohibited']` pour `strava_id`, `distance`, `moving_time`, `elapsed_time`, `total_elevation_gain`, `average_speed`, `max_speed`, `average_heartrate`, `max_heartrate`, `kilojoules`, `started_at` ; `name` `['string', 'max:255']` et `sport_type` `[Rule::enum(SportType::class)]` restent ; `RebuildUserCoverage::handle(string $userId): int` (signature et valeur de retour inchangées, désormais convergente).
- Consumes: `Resource::serverManagedFieldRules()` (Task 1), `RejectsApiCreation`.

- [ ] **Step 1: tests qui échouent** —
  - data provider des 12 champs interdits : mise à jour par le propriétaire d'une activité (`distance` 10 000) → 422 sur `mutate.0.attributes.<champ>`, valeur inchangée (`distance` → `5000000` laisse `10000`) ;
  - mise à jour `name` `Sortie corrigée` et `sport_type` `Ride` → 200, valeurs enregistrées ;
  - cellules : `create` → 422 et 0 ligne (`it_rejects_creating_an_explored_cell_through_the_api`) ; mise à jour de `visit_count` par le propriétaire → 403, `visit_count` toujours 1 ; `deleteJson('/api/explored-cells', ['resources' => [$cell->id]])` par le propriétaire → 403, ligne présente ; la recherche renvoie toujours ses seules cellules ;
  - reconstruction (polyline de `it_builds_explored_cells_from_activity_polylines` → 3 cellules) : une cellule forgée de clé `999999:999999` du même utilisateur disparaît, les 3 cellules dérivées restent ; la cellule forgée d'un autre utilisateur est intacte ; deux reconstructions → mêmes `id` et `created_at` pour les cellules dérivées ; utilisateur sans activité à tracé avec 2 cellules → 0 cellule, `handle()` renvoie 0.
- [ ] **Step 2:** FAIL. **Step 3:** après l'upsert, clés obsolètes = `ExploredCell::query()->where('user_id', $userId)->pluck('cell_key')->diff(array_keys($aggregated))`, suppression par `chunk(500)` avec `whereIn('cell_key', …)`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Sport`, `tests/Feature/Exploration`, `tests/Feature/Lomkit` verts ; commit `🔒 lock strava-synced activity fields and make explored cells a read-only convergent projection`.

### Task 5: Agrégation SQL des métriques d'Objectifs

**Files:**
- Create: `functional/goals/src/Services/GoalMetricAggregator.php`, `functional/goals/src/Services/Dto/MeasurementPeriod.php`, `functional/goals/src/Services/Dto/MetricAggregate.php`, `functional/goals/src/Exceptions/UnaggregatableGoalMetricException.php`
- Modify: `functional/goals/src/Enums/GoalMetric.php` (`isSelfReported()`), `functional/goals/src/Services/GoalProgressCalculator.php` (`measure()` délègue ; suppression de `sportActivities()`, `motoRides()`, `exploredCellsCount()` et des dépendances `SportStatisticsCalculator`, `RidingStatsCalculator`)
- Test: `tests/Feature/Goals/GoalMetricAggregatorTest.php` (nouveau), ajouts dans `tests/Unit/Goals/GoalProgressCalculatorTest.php`

**Interfaces:**
- Produces: `final readonly class MeasurementPeriod(CarbonInterface $startsAt, CarbonInterface $endsAt, ?CarbonInterface $recordedBefore = null)` (demi-ouvert ; `recordedBefore` → `recorded_at < recordedBefore`, colonne `moto_rides.recorded_at` posée par le serveur à la création et à chaque changement de `started_at` ou `distance`, Task 4b, jamais `created_at`) ; `final readonly class MetricAggregate(string $model, string $dateColumn, ?string $summedColumn, float $divisor, ?string $recordedColumn = null)` (`@param class-string<Model> $model`, `summedColumn` nul = `count(*)`, `recordedColumn` = `recorded_at` pour les deux agrégats moto, nul pour les autres, jamais filtrés) avec `expression(Grammar $grammar): string` ; `GoalMetricAggregator::total(GoalMetric $metric, string $userId, ?CarbonInterface $from, ?CarbonInterface $until): float` (bornes inclusives) et `totalsPerPeriod(GoalMetric $metric, string $userId, array $periods): array` (`@param list<MeasurementPeriod>`, `@return list<float>`, une requête, aucune pour `[]`), tous deux `@throws UnboundedGoalMetricException|UnaggregatableGoalMetricException`, docblocks de danger « bounds are converted to the application timezone before binding, a zoned Carbon would shift the period » et « a row inside two overlapping periods is counted in the first only » ; table des sept agrégats du §8.2 de la spec ; `GoalMetric::isSelfReported(): bool` (vrai pour `MotoDistance`, `MotoRideCount`, `FinanceInvestedCapital`, `FinancePortfolioValue`, `Manual`, `TodoCompletionRate`) ; `UnaggregatableGoalMetricException(GoalMetric $metric)` pour `FinanceInvestedCapital`, `UnboundedGoalMetricException` pour les trois métriques non bornées.
- Consumes: rien.

- [ ] **Step 1: tests qui échouent** — périodes W39 `[2026-09-20 22:00:00, 2026-09-27 22:00:00[`, W40 `[2026-09-27 22:00:00, 2026-10-04 22:00:00[`, W41 `[2026-10-04 22:00:00, 2026-10-11 22:00:00[` :
  - activités de 10 000 m à `2026-09-21 12:00:00`, 5 000 m à `2026-09-27 21:59:59`, 7 000 m à `2026-09-27 22:00:00` et 9 000 m d'un autre utilisateur → `totalsPerPeriod(SportDistance, …, [W39, W40, W41])` = `[15.0, 7.0, 0.0]`, exactement 1 requête (`DB::enableQueryLog`) ;
  - sorties de 100 km datée `2026-09-22 08:00:00` enregistrée (`recorded_at`) `2026-09-23 08:00:00` et de 50 km datée `2026-09-23 08:00:00` enregistrée `2026-10-02 08:00:00` → W39 avec `recordedBefore` `2026-09-29 22:00:00` = `[100.0]`, sans `recordedBefore` = `[150.0]` ; une sortie supprimée (soft delete) n'est jamais comptée ;
  - `SportMovingTime` 5 400 s + 1 800 s → `[2.0]` ; `SportElevation` 300 + 450 → `[750.0]` ; `SportActivityCount` 3 activités → `[3.0]` ; `MotoRideCount` 2 sorties → `[2.0]` ; `ExplorationCells` 2 cellules `first_seen_at` en W40 et 1 en W41, périodes `[W40, W41]` → `[2.0, 1.0]` ;
  - bornes zonées `Europe/Paris` (`2026-09-28 00:00:00` → `2026-10-05 00:00:00`) → même résultat que W40 UTC ;
  - `totalsPerPeriod(…, [])` → `[]`, 0 requête ; `FinanceInvestedCapital` → `UnaggregatableGoalMetricException` ; `Manual` → `UnboundedGoalMetricException` ;
  - `total(SportDistance, …, 2026-09-27 22:00:00, 2026-10-04 21:59:59)` avec une activité de 3 000 m à `2026-10-04 21:59:59` → `3.0` (borne incluse) ;
  - `GoalProgressCalculator::measure(MotoDistance, …)` → une seule requête dont le SQL contient `sum(` ; `isSelfReported()` sur les 11 cas (data provider).
- [ ] **Step 2:** FAIL. **Step 3:** `totalsPerPeriod` sur `$aggregate->model::query()` (portée soft delete conservée) : `where('user_id', $userId)`, bornes globales `>=` min / `<` max, `selectRaw('case when … then <index> … end as period_index, <expression> as total', $bindings)`, `groupBy('period_index')`, `toBase()->get()`, lignes d'index nul ignorées, totaux `(float) / divisor`, zéros pour les périodes vides ; `measure()` : sept métriques → `total()`, `FinanceInvestedCapital` → `CapitalCalculator` (inchangé), non bornées → `UnboundedGoalMetricException`.
- [ ] **Step 4:** PASS, et `php artisan test --compact tests/Unit/Goals tests/Feature/Goals tests/Feature/Gamification/WeeklyMetricMeterTest.php tests/Feature/Gamification/ProposeWeeklyChallengesTest.php tests/Feature/Gamification/ResolveChallengesTest.php` vert **sans modification des tests existants** ; commit `⚡ aggregate bounded goal metrics in sql and add a grouped per-period variant`.

### Task 6: Clôture figée sur chaque défi

**Files:**
- Create: `functional/gamification/database/migrations/2026_10_05_000001_add_closes_at_to_challenges_table.php`
- Modify: `functional/gamification/src/Models/Challenge.php` (cast `datetime`, `@property Carbon $closes_at`), `functional/gamification/database/Factories/ChallengeFactory.php`, `functional/gamification/src/Services/Dto/GamificationWeek.php` (`closesAt()` remplace `isPastGrace()`), `functional/gamification/src/Actions/ProposeWeeklyChallenges.php` (`row()`), `functional/gamification/src/Actions/ResolveChallenges.php`
- Test: `tests/Feature/Gamification/ChallengeClosesAtMigrationTest.php` (nouveau), ajouts dans `ChallengeModelTest`, `ProposeWeeklyChallengesTest`, `ResolveChallengesTest` ; dans `GamificationWeekTest`, les assertions `isPastGrace` deviennent des assertions `closesAt` ; dans `RunChallengeCycleTest`, `it_reads_the_closing_grace_on_every_call` devient `it_keeps_the_closing_grace_frozen_on_each_challenge`

**Interfaces:**
- Produces: colonne `challenges.closes_at` (timestamp non nul, après `ends_at`) ; `GamificationWeek::closesAt(int $graceHours): CarbonImmutable` (`endsAt->addHours($graceHours)`) ; ligne de proposition `closes_at = $week->closesAt($settings->closingGraceHours)` ; `ResolveChallenges` : `gracePassed: $moment->greaterThanOrEqualTo($challenge->closes_at)`, plus aucune lecture de `ChallengeSettings` ; `ChallengeFactory` : `closes_at` = `fn (array $attributes): CarbonImmutable => CarbonImmutable::parse($attributes['ends_at'])->addHours(ChallengeSettings::fromConfig()->closingGraceHours)`.
- Consumes: `ChallengeSettings::fromConfig()`, `GamificationWeek`.

- [ ] **Step 1: tests qui échouent** —
  - semaine : W40 `closesAt(48)` → `2026-10-06 22:00:00` UTC, `closesAt(0)` → `2026-10-04 22:00:00` ; W43 `closesAt(48)` → `2026-10-27 23:00:00` ;
  - proposition W40 (`travelTo(2026-09-28 06:00:00)`, historique sport du plan G4) → `closes_at` `2026-10-06 22:00:00` ; avec `closing_grace_hours` 72 → `2026-10-07 22:00:00` ;
  - factory : défi par défaut de W40 → `closes_at` `2026-10-06 22:00:00` ; `forWeek(W39)` → `2026-09-29 22:00:00` ; cast en Carbon ;
  - figé : défi W40 accepté créé sous 48 h, puis `closing_grace_hours` 0 et passage à `2026-10-05 10:00:00` → toujours `Accepted` ; puis `closing_grace_hours` 168 et passage à `2026-10-06 22:00:00` → `Failed` ; `RunChallengeCycle` : même scénario au niveau du cycle (test renommé) ;
  - migration : `$migration = require base_path('functional/gamification/database/migrations/2026_10_05_000001_add_closes_at_to_challenges_table.php')`, `down()`, insertion par `Challenge::query()->insert()` de deux défis sans `closes_at` (`ends_at` `2026-09-27 22:00:00` et `2026-10-04 22:00:00`), `up()` → `2026-09-29 22:00:00` et `2026-10-06 22:00:00` ; avec `closing_grace_hours` 72 avant `up()` → `2026-09-30 22:00:00` et `2026-10-07 22:00:00` ; après `up()`, insérer un défi sans `closes_at` lève `QueryException`.
- [ ] **Step 2:** FAIL. **Step 3:** migration : `timestamp('closes_at')->nullable()->after('ends_at')`, puis `DB::table('challenges')->whereNull('closes_at')->distinct()->pluck('ends_at')` et, pour chaque valeur, `update(['closes_at' => Carbon::parse($endsAt, 'UTC')->addHours($grace)->toDateTimeString()])` avec `$grace = ChallengeSettings::fromConfig()->closingGraceHours`, puis `timestamp('closes_at')->nullable(false)->change()` ; `down()` → `dropColumn('closes_at')`. Supprimer `GamificationWeek::isPastGrace()`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Gamification` entier vert ; commit `🐛 freeze the closing instant on each challenge instead of reading the grace at resolution`.

### Task 7: Historique groupé et règle d'enregistrement à temps

**Files:**
- Modify: `functional/gamification/src/Services/WeeklyMetricMeter.php`, `functional/gamification/src/Actions/ProposeWeeklyChallenges.php` (suppression de `hasHistory()`, `weeklyValues()` via `history()`), `functional/gamification/src/Actions/ResolveChallenges.php` (mesure avec `closes_at`)
- Test: `tests/Feature/Gamification/WeeklyMetricMeterTest.php` (appels adaptés à la nouvelle signature, ajouts), ajouts dans `ProposeWeeklyChallengesTest` et `ResolveChallengesTest` ; `it_measures_the_weeks_of_the_templates_with_data_only` devient `it_runs_one_grouped_history_query_per_template_whatever_the_history`

**Interfaces:**
- Produces: `WeeklyMetricMeter::measure(User $user, GoalMetric $metric, CarbonInterface $startsAt, CarbonInterface $endsAt, CarbonInterface $closesAt): float` (une période, `recordedBefore = $closesAt` si `$metric->isSelfReported()`, `round(…, 2)`) ; `WeeklyMetricMeter::history(User $user, GoalMetric $metric, array $weeks, int $closingGraceHours): array` (`@param list<GamificationWeek>`, `@return list<float>` dans l'ordre reçu, une requête, `recordedBefore = $week->closesAt($closingGraceHours)` si déclarative, `round(…, 2)`) ; `measureWeek()` supprimée ; `ProposeWeeklyChallenges` : 1 requête d'existence + 7 historiques, + insertion et relecture.
- Consumes: `GoalMetricAggregator::totalsPerPeriod`, `MeasurementPeriod` (`recordedBefore` filtre `moto_rides.recorded_at`), `GoalMetric::isSelfReported` (Task 5) ; `GamificationWeek::closesAt`, `challenges.closes_at` (Task 6) ; `recorded_at` de `MotoRideFactory` aligné sur `started_at` comme `created_at` (Tasks 1 et 4b, garde verts les tests G4 de l'historique moto) ; les tests d'antidatage posent `recorded_at` explicitement, jamais `created_at`.

- [ ] **Step 1: tests qui échouent** (proposition à `travelTo(2026-09-28 06:00:00 UTC)`, W40) —
  - sorties de 100 / 120 / 140 / 160 km les 02/09, 09/09, 16/09, 23/09 à 12:00, celle du 16/09 enregistrée (`recorded_at`) `2026-09-22 22:00:00` → `moto_distance` base `110.00`, cible `130.00` ; enregistrée `2026-09-22 21:59:59` → base `130.00`, cible `150.00` ; celle du 23/09 enregistrée `2026-09-28 05:00:00` → comptée ; sortie du 16/09 saisie à temps puis dont la distance est modifiée `2026-09-30 10:00:00` → hors délai, base `110.00` ;
  - utilisateur sans historique moto qui enregistre au moment de la proposition (`recorded_at` `2026-09-28 06:00:00`) des sorties datées `2026-09-16 12:00:00` et `2026-09-23 12:00:00` → aucune ligne `moto_*` ;
  - activités sport de 10 / 20 / 30 / 40 km datées W36–W39, toutes créées `2026-09-28 05:00:00` → `sport_distance` base `25.00`, cible `28.00` ;
  - budget : historique sport + moto + exploration → exactement 10 requêtes (1 existence + 7 historiques + insertion + relecture) ; utilisateur sans donnée → exactement 8 ;
  - meter : `history(user, MotoDistance, [W36, W37, W38, W39], 48)` avec la sortie du 16/09 hors délai → `[100.0, 120.0, 0.0, 160.0]` en 1 requête ; même cas en `SportDistance` (activités créées hors délai) → valeurs complètes ;
  - résolution, défi `moto_distance` W40 accepté, cible 150, `closes_at` `2026-10-06 22:00:00` : sortie de 160 km datée `2026-10-04 08:00:00` enregistrée `2026-10-06 21:59:59`, passage à `21:59:59` → `Completed`, `current_value` `160.00` ; enregistrée `2026-10-06 22:00:00`, passage à `22:00:00` → `Failed`, `current_value` `0.00` ; sortie de 160 km datée `2026-09-29 08:00:00` enregistrée `2026-10-03 10:00:00`, passage à `2026-10-03 10:00:00` → `Completed` (antidatage dans la semaine, risque accepté) ;
  - résolution, défi `sport_distance` W40 accepté, cible 28 : activité de 30 000 m datée `2026-10-04 15:00:00` créée `2026-10-07 01:00:00`, passage à `2026-10-07 01:00:00` → `Completed` (règle G4 des synchronisations tardives inchangée).
- [ ] **Step 2:** FAIL. **Step 3:** `eligibleTargets()` : pour chaque template, `history($user, $template->metric(), $weeks, $settings->closingGraceHours)` avec `$weeks` = `range($settings->historyWeeks, 1)` → `$week->previous($weeksBack)`, puis `ChallengeTargetCalculator::target()` ; `ResolveChallenges` : `measure($user, $challenge->metric, $challenge->starts_at, $challenge->ends_at, $challenge->closes_at)`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Gamification` entier vert ; commit `🔒 build the challenge history in one grouped query per template and ignore self-reported rows recorded after their week closed`.

### Task 8: Instantané de mesure sur les attributions de badges

**Files:**
- Create: `functional/gamification/database/migrations/2026_10_05_000002_add_measured_value_to_badge_awards_table.php`
- Modify: `functional/gamification/src/Models/BadgeAward.php` (`fillable`, cast `float`, `@property float|null $measured_value`), `functional/gamification/database/Factories/BadgeAwardFactory.php` (`measured_value` nul), `functional/gamification/src/Actions/EvaluateBadges.php` (`award()`)
- Test: ajouts dans `tests/Feature/Gamification/EvaluateBadgesTest.php`, `BadgeModelTest.php`, `GamificationApiScopeTest.php`

**Interfaces:**
- Produces: colonne `badge_awards.measured_value` (`double`, nullable, après `awarded_at`, sans backfill) ; chaque ligne insérée par `EvaluateBadges::award()` porte `measured_value = round($measures[$badge->rule_key], 2)` ; jamais mise à jour ensuite ; non exposée par `BadgeAwardResource`.
- Consumes: rien.

- [ ] **Step 1: tests qui échouent** —
  - activités totalisant 120 000 m → attribution `sport_distance_bronze` avec `measured_value` `120.0` ;
  - 1 200 000 m → Bronze et Argent créés dans le même passage, tous deux à `1200.0` ;
  - Bronze obtenu à `120.0`, ajout d'activités jusqu'à 1 500 km, second passage → Bronze toujours `120.0`, Argent créé à `1500.0` ;
  - attribution de factory à `measured_value` nul → reste nulle après un passage ;
  - transaction plafonnée 1 000 000 000 × 10 000 000 → Or `finance_invested_capital` avec `measured_value` `1.0E16`, sans erreur ; `Schema::getColumnType('badge_awards', 'measured_value')` = `double` ;
  - `/api/badge-awards/search` : la réponse ne contient pas `measured_value`.
- [ ] **Step 2:** FAIL. **Step 3:** migration `double('measured_value')->nullable()->after('awarded_at')`, `down()` → `dropColumn` ; ajouter `measured_value` au tableau de lignes de `award()`.
- [ ] **Step 4:** PASS, plus `EvaluateBadgesTest`, `BadgeModelTest`, `GamificationApiScopeTest`, `RunUserGamificationTest` verts ; commit `✨ snapshot the measured value on each badge award for later audits`.

### Task 9: Vérifications finales, env local et PR

- [ ] Merger `origin/develop` s'il a bougé ; résoudre et relancer les tests touchés.
- [ ] `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur, aucun ajout au baseline), `php artisan test --compact` (tout vert).
- [ ] Allumer l'env local, donner à Quentin l'adresse, les identifiants de test et les liens directs vers `/moto` (formulaire : date future et 2 500 km refusés), `/finance` (vente à 0 € refusée), `/todo` (cocher une tâche), `/exploration` (« Recalculer ») et `/player` (défis et badges) ; attendre sa validation.
- [ ] Après son accord : `gh pr create --base develop --title "🔒 Anti-cheat hardening before the social phase"`, en signalant dans la description l'opération à lancer une fois sur le serveur après livraison (`php artisan exploration:rebuild-coverage`), puis couper l'env (`ss -ltnp "sport = :<port>"`, tuer le parent puis l'enfant, vérifier que le port est libre).

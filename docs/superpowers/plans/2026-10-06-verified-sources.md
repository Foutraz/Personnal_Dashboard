# Sources vérifiées — pré-requis G5 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire de « synchronisé » un « vérifié » avant G5 : les SDK `foutraz/withings` et `foutraz/strava` exposent la provenance (attribution Withings, activité manuelle / signalée / téléversée Strava), le tableau de bord stocke un verdict par ligne et n'en nourrit le jeu que s'il est vérifié, un compte fournisseur actif n'alimente qu'un compte du tableau de bord, et le classement d'une période close est figé par un instantané.

**Architecture:** Deux releases mineures de SDK (1.1.0, champs ajoutés en fin de constructeur avec `null` par défaut). Le verdict `Technical\Integrations\Enums\SourceVerification` est calculé par `UpsertStravaActivity` / `UpsertBodyMeasurement` à partir des seules données du fournisseur et stocké dans `verification` ; les règles d'XP, de badges, la couverture d'exploration et la mesure des défis filtrent `verified`, les Objectifs et les écrans de suivi non. L'unicité active repose sur une colonne générée virtuelle `active_marker` et deux index composites. La gamification gagne des fenêtres de classement, un total d'XP vérifiée en une requête groupée et des instantanés écrits une fois par une commande horaire.

**Tech Stack:** Laravel 12.62 / PHP 8.4, layers OSDD `technical/integrations`, `functional/{sport,health,exploration,goals,gamification}`, Livewire 3, lomkit/laravel-rest-api, PHPUnit 11/12, Pint, Larastan niveau 7 ; MySQL en production, SQLite (3.45) en tests ; SDK framework-agnostiques Guzzle 7 + PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-10-06-verified-sources-design.md`

## Global Constraints

- Tableau de bord : worktree `/home/qmari/Projects/perso/.dashboard-worktrees/verified-sources`, branche `feature/verified-sources`, empilée sur `feature/anti-cheat-hardening` ; merger `origin/feature/anti-cheat-hardening` tant qu'elle n'est pas mergée, puis `origin/develop` (jamais de rebase) ; ne jamais toucher aux autres worktrees ni à l'arbre principal.
- SDK : Withings cloné dans `/home/qmari/Projects/perso/SDK_Withings` (`git clone https://github.com/Foutraz/SDK_Withings.git`), worktree `/home/qmari/Projects/perso/.sdk-withings-worktrees/measure-provenance` ; Strava depuis `/home/qmari/Projects/perso/SDK_Strava` (`core.autocrlf=true`, garder les fins de ligne du dépôt), worktree `/home/qmari/Projects/perso/.sdk-strava-worktrees/activity-provenance` ; branches depuis `origin/main`, jamais de commit sur `main` ; PR `--base main`, merge `gh pr merge <n> --squash --delete-branch` une fois vert, tag `1.1.0` (sans `v`, comme `1.0.0`) sur le commit de merge, release GitHub avec la section du changelog.
- Composer du tableau de bord : **Quentin lance** `composer require foutraz/strava:^1.1 foutraz/withings:^1.1 --no-interaction` (dépôts VCS, authentification GitHub) ; l'agent ne lance ni `composer require` ni `composer update` dans le tableau de bord.
- Commits séparés d'une phrase en anglais avec gitmoji, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, push après chaque commit (dépôts SDK compris).
- Aucun commentaire inline ; docblock d'une phrase en anglais seulement s'il porte ce que la signature ne porte pas (type générique, `@throws`, danger nommé) ; pas de try-catch ; ULID partout ; aucune cascade ; exceptions nommées ; aucune chaîne magique (enums, `whereBelongsTo`, constantes `Response::HTTP_*`) ; écouteurs de cycle de vie branchés par nom de classe (`IntegrationConnection::deleting(DeleteConnectionBodyMeasurements::class)`), jamais d'Observer.
- Migrations ré-exécutables (`Schema::hasColumn`, `Schema::hasIndex`, `Schema::hasTable`), sans fonction de date SQL, sans `CONCAT` ni `||`, sans enum SQL ; valeurs d'enum par `Enum::Case->value` ; `DB::table()` réservé aux migrations.
- Requêtes de jeu sur les modèles (`Model::query()`, `->toBase()`), filtre `where('verification', SourceVerification::Verified)` ; bornes de fenêtres déjà en UTC avant liaison.
- Tests PHPUnit en classes, `#[Test]`, `it_...`, factories, helper `faker()`, temps figé par `$this->travelTo(Carbon::parse('…', 'UTC'))`. **Aucune méthode de test existante n'est modifiée** : les nouveaux cas s'ajoutent en nouvelles méthodes. Après chaque tâche : `vendor/bin/pint --dirty --format agent` puis les fichiers de test de la tâche et ceux de son Step 4.
- Jalons temporels (UTC) : W39 `[2026-09-20 22:00:00, 2026-09-27 22:00:00[`, clôture `2026-09-29 22:00:00` ; W40 `[2026-09-27 22:00:00, 2026-10-04 22:00:00[`, clôture `2026-10-06 22:00:00` ; W41 `[2026-10-04 22:00:00, 2026-10-11 22:00:00[`, clôture `2026-10-13 22:00:00` ; septembre `[2026-08-31 22:00:00, 2026-09-30 22:00:00[`, clôture `2026-10-02 22:00:00` ; octobre `[2026-09-30 22:00:00, 2026-10-31 23:00:00[`, clôture `2026-11-02 23:00:00` (grâce 48 h).

## Review Focus

- Une charge utile Strava sans clé `manual` (réponses anciennes, fixtures) doit donner `unknown`, jamais `verified` — Task 4, `it_leaves_an_activity_without_the_manual_flag_unknown`.
- Une attribution Withings hors de la liste documentée (15) doit donner `unknown`, jamais `verified` ni `self_reported` — Task 5, data provider.
- Se reconnecter à un fournisseur après une déconnexion doit réussir (aujourd'hui une violation d'unicité, 500) — Task 3, `it_relinks_the_athlete_after_a_disconnect`.
- Un planificateur arrêté plusieurs périodes : le passage suivant rattrape dans l'ordre, un second passage n'écrit rien — Task 9, `it_catches_up_every_closed_window_in_order` et `it_writes_nothing_on_a_second_run`.
- Le mois d'octobre traverse le passage à l'heure d'hiver (fin `2026-10-31 23:00:00`) et les bornes sont demi-ouvertes (`2026-10-04 22:00:00` est en W41) — Task 8, `LeaderboardWindowTest` et `VerifiedXpTotalsTest`.

---

### Task 1: `foutraz/withings` 1.1.0 — provenance des groupes de mesures, catégorie réelle, pagination

**Files (dépôt `Foutraz/SDK_Withings`, worktree `/home/qmari/Projects/perso/.sdk-withings-worktrees/measure-provenance`, branche `feature/measure-provenance`):**
- Create: `.gitignore` (`/vendor/`, `/.phpunit.cache/`, `composer.lock`), `phpunit.xml` (copie de celui de `SDK_Strava` : suites `Unit` et `Feature`, `bootstrap="vendor/autoload.php"`), `tests/TestCase.php`, `tests/Unit/Dto/MeasurementTest.php`, `tests/Unit/Enums/MeasureAttributionTest.php`, `tests/Feature/Actions/ManagesMeasurementsTest.php`, `src/Enums/MeasureAttribution.php`, `src/Enums/MeasureCategory.php`, `CHANGELOG.md`
- Modify: `src/Dto/Measurement.php`, `src/Actions/ManagesMeasurements.php`

**Interfaces:**
- Produces: `enum MeasureAttribution: int` — `Device = 0`, `DeviceAmbiguous = 1`, `ManualEntry = 2`, `ManualAtAccountCreation = 4`, `AutomaticBloodPressure = 5`, `ConfirmedByUser = 7`, `SameAsDevice = 8`, `isDeviceCaptured(): bool` (vrai pour 0, 1, 5, 7, 8) ; `enum MeasureCategory: int` — `Real = 1`, `UserObjective = 2` ; `Measurement::__construct(int $externalId, int $type, float $value, int $unit, DateTimeImmutable $measuredAt, ?int $attrib = null, ?int $category = null, ?string $deviceId = null, ?DateTimeImmutable $createdAt = null)`, `Measurement::attribution(): ?MeasureAttribution` (`tryFrom`) ; `ManagesMeasurements::getmeas(int $userid, ?int $lastUpdate = null, MeasureCategory $category = MeasureCategory::Real): array` (`@return array<int, Measurement>`, `@throws ActionFailed` en plus des existants) ; `tests/TestCase::managerWithResponses(array $responses): WithingsManager`, `jsonResponse(int $status, array $body): Response`, `requestBodies(): list<array<string, string>>` (`parse_str` du corps de chaque requête de l'historique Guzzle).
- Consumes: rien.

- [ ] **Step 0: dépôt et harnais** — cloner, `git -C /home/qmari/Projects/perso/SDK_Withings worktree add /home/qmari/Projects/perso/.sdk-withings-worktrees/measure-provenance -b feature/measure-provenance origin/main`, `composer install` dans le worktree (si GitHub demande une authentification, demander à Quentin). Écrire `.gitignore`, `phpunit.xml`, `tests/TestCase.php` (`MockHandler` + `Middleware::history`, client `base_uri` `https://wbsapi.withings.net/`, manager `('https://wbsapi.withings.net', 'access-token', 'client-id', 'client-secret', 'https://example.test/callback', $client)`) et un premier test sur le code actuel, `ManagesMeasurementsTest::it_maps_every_measure_of_a_group` (groupe `grpid` 111, `date` 1700000000, mesures `['type' => 1, 'value' => 70500, 'unit' => -3]` et `['type' => 6, 'value' => 215, 'unit' => -1]` → 2 mesures, valeurs `70.5` et `21.5` à 0,001 près, `externalId` 111 pour les deux). `vendor/bin/phpunit` → PASS ; commit `✅ add a phpunit harness replaying guzzle mock responses`.
- [ ] **Step 1: tests qui échouent** —
  - DTO : groupe `['grpid' => 111, 'attrib' => 2, 'date' => 1700000000, 'created' => 1700003600, 'category' => 1, 'deviceid' => null]` + mesure poids → `attrib` 2, `category` 1, `deviceId` null, `createdAt->format('c')` `2023-11-14T23:13:20+00:00`, `measuredAt` `2023-11-14T22:13:20+00:00`, `attribution()` `ManualEntry` ; `attrib` 0 et `deviceid` `a1b2c3d4` → `deviceId` `a1b2c3d4`, `attribution()` `Device` ; groupe `['grpid' => 5, 'date' => 1700000000]` → les quatre champs nuls, `attribution()` nul ; `attrib` 15 → `attrib` 15, `attribution()` nul ; `new Measurement(1, 1, 70.5, -3, new DateTimeImmutable('@1700000000'))` (constructeur 1.0) → `attrib` nul ;
  - enum : data provider `0 → true`, `1 → true`, `2 → false`, `4 → false`, `5 → true`, `7 → true`, `8 → true` sur `isDeviceCaptured()` ;
  - `getmeas(42)` → `requestBodies()[0]` = `['action' => 'getmeas', 'userid' => '42', 'category' => '1']` ; `getmeas(42, 1700000000, MeasureCategory::UserObjective)` → `lastupdate` `1700000000`, `category` `2` ;
  - pagination : page 1 `measuregrps` [grp 1, grp 2], `more` 1, `offset` 2 ; page 2 [grp 3], `more` 0 → 2 requêtes, la seconde porte `offset` `2`, `externalId` dans l'ordre `[1, 2, 3]` ;
  - `more` 1 sans `offset` → `ActionFailed` « Withings getmeas pagination did not advance. » après 1 requête ; page 1 `more` 1 `offset` 2 puis page 2 `more` 1 `offset` 2 → même exception après 2 requêtes ;
  - `status` 503 et `error` `Invalid params` → `ActionFailed` `Invalid params` (comportement existant épinglé).
- [ ] **Step 2:** `vendor/bin/phpunit` → FAIL.
- [ ] **Step 3: implémenter** — `fromArray()` lit `attrib`, `category`, `deviceid`, `created` (`new DateTimeImmutable('@'.$grp['created'])` si présent) ; `getmeas()` :

```php
$payload = ['action' => 'getmeas', 'userid' => $userid, 'category' => $category->value];

if ($lastUpdate !== null) {
    $payload['lastupdate'] = $lastUpdate;
}

$measurements = [];
$offset = 0;

do {
    $body = $this->postForm('https://wbsapi.withings.net/measure', $payload)['body'] ?? [];

    foreach ($body['measuregrps'] ?? [] as $grp) {
        foreach ($grp['measures'] ?? [] as $measure) {
            $measurements[] = Measurement::fromArray($grp, $measure);
        }
    }

    $hasMore = (bool) ($body['more'] ?? false);

    if ($hasMore) {
        $offset = $this->nextOffset($body, $offset);
        $payload['offset'] = $offset;
    }
} while ($hasMore);

return $measurements;
```

  `nextOffset(array $body, int $previous): int` lève `new ActionFailed('Withings getmeas pagination did not advance.')` si `offset` est absent ou `≤ $previous` (docblock `@throws ActionFailed`).
- [ ] **Step 4:** `vendor/bin/phpunit` → PASS ; commits `✨ expose the attribution, category, device and creation time of measure groups` (DTO, enums, tests DTO / enum) puis `🐛 request real measures only and follow the getmeas pages until the last one` (action, tests action).
- [ ] **Step 5: release** — `CHANGELOG.md` (Keep a Changelog) : `## [1.1.0] - 2026-10-06` Added (`Measurement::$attrib`, `$category`, `$deviceId`, `$createdAt`, `attribution()`, `MeasureAttribution`, `MeasureCategory`, suite PHPUnit), Changed (`getmeas` requests real measures only by default, pass `MeasureCategory::UserObjective` for objectives), Fixed (`getmeas` follows `more` / `offset` pages and fails on a stalled offset) ; `## [1.0.0]` Initial release. Commit `📝 add a changelog for the 1.1.0 release`. `gh pr create --repo Foutraz/SDK_Withings --base main --title "✨ Measure group provenance, real measures only and getmeas pagination"`, merge une fois vert, puis `git -C /home/qmari/Projects/perso/SDK_Withings fetch origin && git -C /home/qmari/Projects/perso/SDK_Withings tag 1.1.0 origin/main && git -C /home/qmari/Projects/perso/SDK_Withings push origin 1.1.0`, `gh release create 1.1.0 --repo Foutraz/SDK_Withings --title 1.1.0 --notes "<section 1.1.0 du changelog>"` ; `gh api repos/Foutraz/SDK_Withings/tags --jq '.[].name'` → contient `1.1.0`. Supprimer le worktree.

### Task 2: `foutraz/strava` 1.1.0 — provenance des activités

**Files (dépôt `Foutraz/SDK_Strava`, worktree `/home/qmari/Projects/perso/.sdk-strava-worktrees/activity-provenance`, branche `feature/activity-provenance`):**
- Modify: `src/Dto/Activity.php`, `README.md` (section Activities), `tests/Unit/Dto/ActivityTest.php` (nouvelles méthodes), `tests/Feature/Actions/ManagesActivitiesTest.php` (nouvelle méthode)
- Create: `CHANGELOG.md`

**Interfaces:**
- Produces: `Activity::__construct(…19 paramètres 1.0 inchangés…, ?bool $isManual = null, ?bool $isFlagged = null, ?bool $isTrainer = null, ?int $uploadId = null, ?string $externalId = null, ?string $deviceName = null)` ; `fromArray()` : booléens `isset($data['manual']) ? (bool) $data['manual'] : null` (idem `flagged`, `trainer`), `uploadId` depuis `upload_id`, à défaut `upload_id_str` (`(int)`), `externalId` / `deviceName` chaînes ou `null`.
- Consumes: rien.

- [ ] **Step 0:** `git -C /home/qmari/Projects/perso/SDK_Strava fetch origin`, `git -C /home/qmari/Projects/perso/SDK_Strava worktree add /home/qmari/Projects/perso/.sdk-strava-worktrees/activity-provenance -b feature/activity-provenance origin/main`, `composer install`, `vendor/bin/phpunit` → vert.
- [ ] **Step 1: tests qui échouent** —
  - charge utile complète de `it_maps_a_full_payload` + `'manual' => false, 'flagged' => false, 'trainer' => false, 'upload_id' => 12345678901, 'upload_id_str' => '12345678901', 'external_id' => 'garmin_push_9876543210', 'device_name' => 'Garmin Edge 830'` → `isManual` false, `isFlagged` false, `isTrainer` false, `uploadId` 12345678901, `externalId` `garmin_push_9876543210`, `deviceName` `Garmin Edge 830` ;
  - `['id' => 7, 'name' => 'Musculation', 'type' => 'Workout', 'manual' => true, 'upload_id' => null, 'external_id' => null]` → `isManual` true, `uploadId` null, `externalId` null ;
  - `'flagged' => true` → `isFlagged` true ; seul `'upload_id_str' => '98765432109876'` → `uploadId` 98765432109876 ;
  - charge utile minimale de `it_defaults_nullable_fields_to_null` → les six nouveaux champs nuls (jamais `false`) ;
  - constructeur positionnel à 19 arguments (valeurs de `it_maps_a_full_payload`) → `isManual` nul ;
  - `list()` sur `[['id' => 1, 'name' => 'A', 'type' => 'Run', 'manual' => true], ['id' => 2, 'name' => 'B', 'type' => 'Ride', 'manual' => false, 'upload_id' => 555]]` → `[0]->isManual` true, `[1]->uploadId` 555.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter ; README : ajouter dans l'exemple `iterate()` les lignes `$activity->isManual; // ?bool, true when created by hand`, `$activity->isFlagged; // ?bool`, `$activity->uploadId; // ?int, set for uploaded files and device syncs`, `$activity->deviceName; // ?string`.
- [ ] **Step 4:** PASS ; commit `✨ expose the manual, flagged, trainer, upload and device provenance of activities` ; `CHANGELOG.md` (`## [1.1.0] - 2026-10-06` Added : les six champs ; `## [1.0.0]` Initial release) + README, commit `📝 document the activity provenance and add a changelog for the 1.1.0 release` ; PR `--base main` titre `✨ Activity provenance fields`, merge, tag `1.1.0` sur `origin/main`, push du tag, release GitHub ; `gh api repos/Foutraz/SDK_Strava/tags --jq '.[].name'` → contient `1.1.0`. Supprimer le worktree.

### Task 3: Un compte fournisseur actif pour un compte du tableau de bord

**Files:**
- Create: `technical/integrations/database/migrations/2026_10_06_000001_enforce_one_dashboard_account_per_provider_account.php`, `technical/integrations/src/Actions/EnsureProviderAccountIsAvailable.php`, `technical/integrations/src/Exceptions/ProviderAccountAlreadyLinkedException.php`, `technical/integrations/src/Exceptions/DuplicateProviderAccountLinksException.php`, `functional/health/src/Listeners/DeleteConnectionBodyMeasurements.php`
- Modify: `functional/sport/src/Actions/FindOrCreateConnection.php`, `functional/health/src/Actions/FindOrCreateWithingsConnection.php`, `functional/health/src/Providers/HealthServiceProvider.php` (`boot()`), `technical/integrations/resources/views/livewire/integrations-manager.blade.php`
- Test: `tests/Feature/Integrations/ProviderAccountUniquenessTest.php`, `tests/Feature/Integrations/ProviderAccountMigrationTest.php`, `tests/Feature/Health/ConnectionDeletionTest.php` (nouveaux) ; nouvelles méthodes dans `tests/Feature/Sport/StravaCallbackTest.php`, `tests/Feature/Health/WithingsCallbackTest.php`, `tests/Feature/Integrations/IntegrationsPageTest.php`

**Interfaces:**
- Produces: colonne virtuelle `integration_connections.active_marker` ; index `integration_connections_user_provider_active_unique` `(user_id, provider, active_marker)` et `integration_connections_provider_account_active_unique` `(provider, external_id, active_marker)` ; `EnsureProviderAccountIsAvailable::handle(string $userId, IntegrationProvider $provider, string $externalId): void` (`@throws ProviderAccountAlreadyLinkedException`) ; `final class ProviderAccountAlreadyLinkedException extends ConflictHttpException` (`__construct(IntegrationProvider $provider)`, message `__('Ce compte :provider est déjà relié à un autre compte du tableau de bord.', ['provider' => $provider->label()])`, `render(): RedirectResponse` → `redirect()->route('integrations')->with('integration_error', $this->getMessage())`) ; `final class DuplicateProviderAccountLinksException extends Exception` (`static forAccounts(Collection $duplicates): self`, message `Active integration connections share a provider account: strava:99887766 (2).`) ; `DeleteConnectionBodyMeasurements::handle(IntegrationConnection $connection): void`.
- Consumes: rien (indépendante des SDK : peut avancer pendant les PR des Tasks 1 et 2).

- [ ] **Step 1: tests qui échouent** —
  - base : connexion Strava active de A `external_id` `99887766`, la même pour B → `UniqueConstraintViolationException` ; A supprimée (soft delete) → création de B acceptée, 2 lignes dont 1 supprimée ; A supprimée puis nouvelle connexion Strava de A → acceptée ; deux connexions Google Calendar sans `external_id` (A et B) → acceptées ; Strava `42` pour A et Withings `42` pour B → acceptées ;
  - garde : avec A active, `handle(B, Strava, '99887766')` → `ProviderAccountAlreadyLinkedException`, `getStatusCode()` 409, message `Ce compte Strava est déjà relié à un autre compte du tableau de bord.` ; `handle(A, …)` et A supprimée → aucune exception ;
  - callback Strava (`it_refuses_linking_an_athlete_already_linked_to_another_account`) : A active `99887766`, `access_token` `a-token` ; B reçoit l'athlète 99887766 → `assertRedirect(route('integrations'))`, `assertSessionHas('integration_error', 'Ce compte Strava est déjà relié à un autre compte du tableau de bord.')`, 0 connexion pour B, `access_token` de A toujours `a-token`, `Queue::assertNothingPushed()` ;
  - `it_relinks_the_athlete_after_a_disconnect` : connexion de A supprimée, A rappelle le callback → redirection `sport`, 1 connexion active à `fresh-access-token`, 1 supprimée ;
  - `it_refreshes_the_tokens_when_the_same_user_relinks_the_same_athlete` : A active, A rappelle → 1 ligne, `fresh-access-token` ;
  - callback Withings : mêmes cas avec `userid` 99887766, message `Ce compte Withings est déjà relié à un autre compte du tableau de bord.`, redirection `health` pour la reliaison ;
  - page : `withSession(['integration_error' => 'Ce compte Strava est déjà relié à un autre compte du tableau de bord.'])->get('/integrations')` → `assertSee` du message ;
  - déconnexion Withings : connexion avec 3 mesures, autre connexion du même utilisateur (fournisseur Strava) avec 1 mesure de factory ; `$connection->delete()` → 0 mesure pour la première, 1 pour l'autre ; `IntegrationsManager::disconnect('withings')` produit le même effet ; `Event::fake()` puis `Event::assertListening('eloquent.deleting: '.IntegrationConnection::class, DeleteConnectionBodyMeasurements::class)` ;
  - migration (`$migration = require base_path('technical/integrations/database/migrations/2026_10_06_000001_enforce_one_dashboard_account_per_provider_account.php')`) : `down()` → `active_marker` absente, `integration_connections_user_id_provider_unique` présent ; deux connexions Strava actives `99887766` (A, B) puis `up()` → `DuplicateProviderAccountLinksException` contenant `strava:99887766 (2)`, `active_marker` toujours absente ; B supprimée puis `up()` → colonne et deux index présents, ancien index absent ; second `up()` → aucune erreur.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Integrations tests/Feature/Sport/StravaCallbackTest.php tests/Feature/Health/WithingsCallbackTest.php tests/Feature/Health/ConnectionDeletionTest.php` → FAIL.
- [ ] **Step 3: implémenter** — migration `up()` (docblock du danger « the new index starting with user_id must exist before the old unique is dropped, MySQL backs the user_id foreign key with it ») :

```php
$duplicates = DB::table('integration_connections')
    ->whereNull('deleted_at')
    ->whereNotNull('external_id')
    ->select('provider', 'external_id', DB::raw('count(*) as links'))
    ->groupBy('provider', 'external_id')
    ->havingRaw('count(*) > 1')
    ->get();

if ($duplicates->isNotEmpty()) {
    throw DuplicateProviderAccountLinksException::forAccounts($duplicates);
}

if (! Schema::hasColumn('integration_connections', 'active_marker')) {
    Schema::table('integration_connections', function (Blueprint $table): void {
        $table->unsignedTinyInteger('active_marker')->nullable()->virtualAs('case when deleted_at is null then 1 end')->after('deleted_at');
    });
}
```

  puis, chacun s'il est absent : `unique(['user_id', 'provider', 'active_marker'], 'integration_connections_user_provider_active_unique')`, `unique(['provider', 'external_id', 'active_marker'], 'integration_connections_provider_account_active_unique')`, et enfin `dropUnique('integration_connections_user_id_provider_unique')` s'il existe ; `down()` (docblock du danger « fails once a user has reconnected a provider, the old unique counts the trashed row ») recrée l'ancien unique s'il est absent, supprime les deux nouveaux puis la colonne. `FindOrCreateConnection` et `FindOrCreateWithingsConnection` reçoivent `EnsureProviderAccountIsAvailable` par le constructeur et l'appellent avant `updateOrCreate` (Strava : seulement si `$token->athlete !== null`). Écouteur : `BodyMeasurement::query()->whereBelongsTo($connection, 'connection')->delete()` ; `HealthServiceProvider::boot()` : `IntegrationConnection::deleting(DeleteConnectionBodyMeasurements::class)`. Vue : bandeau `@if (session('integration_error'))` (bordure et texte `rose`, mêmes classes que le bandeau `lime` de `$status`).
- [ ] **Step 4:** PASS, plus `tests/Feature/Integrations`, `tests/Feature/Sport`, `tests/Feature/Health`, `tests/Feature/Planning`, `tests/Feature/Finance` verts sans modification ; commits `🔒 allow one active dashboard account per provider account and let a user reconnect after a disconnect` (migration, exceptions, garde, callbacks, vue, tests associés) puis `🔒 delete the withings measurements of a disconnected connection` (écouteur, provider, test).

### Task 4: Provenance des activités Strava (montée de version des SDK, verdict partagé)

**Files:**
- Create: `technical/integrations/src/Enums/SourceVerification.php`, `functional/sport/database/migrations/2026_10_06_000002_add_provenance_to_sport_activities_table.php`
- Modify: `composer.json`, `composer.lock` (par Quentin), `functional/sport/src/Enums/SportType.php`, `functional/sport/src/Models/SportActivity.php` (`fillable`, casts, `@property`), `functional/sport/src/Actions/UpsertStravaActivity.php`, `functional/sport/database/Factories/SportActivityFactory.php`
- Test: `tests/Feature/Sport/StravaActivityProvenanceTest.php`, `tests/Feature/Sport/ActivityProvenanceMigrationTest.php` (nouveaux), nouvelles méthodes dans `tests/Feature/Sport/SyncActivitiesJobTest.php` et `tests/Feature/Sport/ActivitiesApiScopeTest.php`

**Interfaces:**
- Produces: `enum SourceVerification: string` — `Verified = 'verified'`, `SelfReported = 'self_reported'`, `Implausible = 'implausible'`, `Unknown = 'unknown'` ; colonnes `sport_activities.is_manual` (booléen nullable), `strava_upload_id` (`unsignedBigInteger` nullable), `strava_external_id`, `strava_device_name` (`string` nullables), `verification` (`string(16)`, défaut `SourceVerification::Unknown->value`), après `strava_id` ; casts `is_manual` `boolean`, `verification` `SourceVerification::class` ; `SportType::plausibleAverageSpeedCeilingKmh(): int` (`Run`, `Walk`, `Hike` → 25, `Swim` → 10, autres → 50) ; factory : `is_manual` false, `verification` `Verified`, états `manual()` (`is_manual` true, `SelfReported`, `map_polyline` nul), `implausible()` (`Implausible`), `ofUnknownProvenance()` (`is_manual` nul, `Unknown`).
- Consumes: `Activity::$isManual`, `$isFlagged`, `$uploadId`, `$externalId`, `$deviceName` (Task 2).

- [ ] **Step 0: montée de version** — demander à Quentin de lancer dans le worktree `composer require foutraz/strava:^1.1 foutraz/withings:^1.1 --no-interaction` ; vérifier `composer show foutraz/strava foutraz/withings` → `1.1.0` ; `php artisan test --compact tests/Feature/Sport tests/Feature/Health` → vert sans aucun changement de code ; commit `⬆️ require the 1.1 strava and withings sdks for synced data provenance` (`composer.json`, `composer.lock`).
- [ ] **Step 1: tests qui échouent** — `app(UpsertStravaActivity::class)($connection, Activity::fromArray([...]))`, data provider sur le tableau du §5.2 de la spec :
  - `Ride` 40 000 m / 3 600 s, `manual` false, `upload_id` 12345678901, `external_id` `garmin_push_9876543210`, `device_name` `Garmin Edge 830` → `verified`, `is_manual` false et les trois faits stockés ;
  - `Ride` 50 000 m / 3 600 s → `verified` ; 60 000 m / 3 600 s → `implausible` ; `Run` 12 500 m / 1 800 s → `verified`, 13 000 m → `implausible` ; `Swim` 2 500 m / 900 s → `verified`, 3 000 m → `implausible` ; `sport_type` `EBikeRide` 30 000 m / 1 800 s → `implausible` ; `Workout` 0 m / 3 600 s → `verified` ; `Ride` 5 000 m / 0 s → `implausible` ; `Ride` 40 000 m / 3 600 s `flagged` true → `implausible` ; `Workout` `manual` true → `self_reported` ;
  - `it_leaves_an_activity_without_the_manual_flag_unknown` : même `Ride` plausible sans clé `manual` → `unknown`, `is_manual` nul ;
  - synchronisation : activité de factory `ofUnknownProvenance()` `strava_id` 1001 sur la connexion, page Strava dont l'activité 1001 porte `manual` false → après `SyncStravaActivitiesJob::dispatchSync` la ligne est `verified` (même `id`) ; page avec `'manual' => true` pour 1002 → `self_reported` ;
  - API : mise à jour par le propriétaire de `verification` `verified` ou de `is_manual` false sur une activité `implausible` → 422 (champs hors de `fields()`), valeurs inchangées ; activité `implausible` dont le propriétaire passe `sport_type` à `Workout` → 200, `verification` toujours `implausible` ;
  - migration : `require` du fichier, `down()`, insertion par `DB::table('sport_activities')` d'une ligne complète sans les nouvelles colonnes, `up()` → `verification` `unknown`, `is_manual` nul ; second `up()` sans erreur.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Sport` → FAIL.
- [ ] **Step 3: implémenter** — `UpsertStravaActivity` écrit `is_manual`, `strava_upload_id`, `strava_external_id`, `strava_device_name` et `verification` = `$this->verification($activity)` :

```php
private function verification(Activity $activity): SourceVerification
{
    if ($activity->isManual === null) {
        return SourceVerification::Unknown;
    }

    if ($activity->isManual) {
        return SourceVerification::SelfReported;
    }

    if ($activity->isFlagged === true || $this->exceedsPlausibleSpeed($activity)) {
        return SourceVerification::Implausible;
    }

    return SourceVerification::Verified;
}

private function exceedsPlausibleSpeed(Activity $activity): bool
{
    if ($activity->movingTime <= 0) {
        return $activity->distance > 0;
    }

    $ceiling = SportType::fromStrava($activity->sportType)->plausibleAverageSpeedCeilingKmh();

    return $activity->distance * 3.6 / $activity->movingTime > $ceiling;
}
```

  Migration : chaque colonne ajoutée seulement si absente ; `down()` les supprime si présentes. `verification` reste hors de `fields()` de `SportActivityResource` et de `TripRouteResource`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Sport`, `tests/Feature/Exploration`, `tests/Feature/Gamification`, `tests/Feature/Goals`, `tests/Unit/Sport` verts sans modification ; commit `✨ store the strava provenance of each activity and a server-computed verification verdict`.

### Task 5: Provenance des mesures Withings

**Files:**
- Create: `functional/health/database/migrations/2026_10_06_000003_add_provenance_to_body_measurements_table.php`
- Modify: `functional/health/src/Models/BodyMeasurement.php`, `functional/health/src/Actions/UpsertBodyMeasurement.php`, `functional/health/src/Jobs/SyncWithingsMeasurementsJob.php`, `functional/health/database/Factories/BodyMeasurementFactory.php`
- Test: `tests/Feature/Health/MeasurementProvenanceTest.php`, `tests/Feature/Health/MeasurementProvenanceMigrationTest.php` (nouveaux)

**Interfaces:**
- Produces: colonnes `body_measurements.attrib` (`unsignedTinyInteger` nullable, après `type`) et `verification` (`string(16)`, défaut `unknown`, après `attrib`) ; casts `attrib` `integer`, `verification` `SourceVerification::class` ; `UpsertBodyMeasurement::__invoke(IntegrationConnection $connection, string $externalId, MeasurementType $type, float $value, Carbon $measuredAt, ?string $unit = null, ?array $raw = null, ?int $attrib = null): BodyMeasurement` (constructeur toujours sans dépendance, `new UpsertBodyMeasurement` reste valide) ; factory : `attrib` `MeasureAttribution::Device->value`, `verification` `Verified`, états `manualEntry()` (`attrib` 2, `SelfReported`) et `ofUnknownProvenance()` (`attrib` nul, `Unknown`).
- Consumes: `SourceVerification` (Task 4), `MeasureAttribution`, `Measurement::$attrib`, `getmeas` (Task 1).

- [ ] **Step 1: tests qui échouent** —
  - data provider sur `UpsertBodyMeasurement` : `attrib` 0, 1, 5, 7, 8 → `verified` ; 2, 4 → `self_reported` ; 15 → `unknown` ; nul (appel sans `attrib`) → `unknown` ; `attrib` stocké tel quel ;
  - ligne `ofUnknownProvenance()` (`external_id` `111`, type poids) puis upsert `attrib` 0 de la même clé → même `id`, `verified` ;
  - job : `getmeas` simulé (même liaison que `SyncWithingsMeasurementsJobTest`) avec le groupe 111 `attrib` 0 et le groupe 112 `attrib` 2, `more` 0 → lignes 111 `verified` et 112 `self_reported` ;
  - migration : `down()`, insertion `DB::table('body_measurements')` sans les nouvelles colonnes, `up()` → `verification` `unknown`, `attrib` nul ; second `up()` sans erreur.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Health` → FAIL.
- [ ] **Step 3: implémenter** — `UpsertBodyMeasurement` écrit `attrib` et `verification` = `match (true) { $attrib === null => Unknown, MeasureAttribution::tryFrom($attrib) === null => Unknown, MeasureAttribution::from($attrib)->isDeviceCaptured() => Verified, default => SelfReported }` dans une méthode privée ; `SyncWithingsMeasurementsJob` passe `attrib: $measurement->attrib`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Health`, `tests/Feature/Gamification` verts sans modification ; commit `✨ store the withings attribution of each measurement and a server-computed verification verdict`.

### Task 6: XP, séries et badges des familles synchronisées sur les seules lignes vérifiées

**Files:**
- Modify: `functional/gamification/src/Xp/Rules/SportActivityXpRule.php`, `functional/gamification/src/Xp/Rules/HealthMeasurementDayXpRule.php`, `functional/gamification/src/Badges/Rules/SportDistanceBadgeRule.php`, `functional/gamification/src/Badges/Rules/SportActivityCountBadgeRule.php`, `functional/gamification/src/Badges/Rules/HealthMeasurementDaysBadgeRule.php`
- Test: `tests/Feature/Gamification/VerifiedSourcesGameTest.php` (nouveau)

**Interfaces:**
- Produces: les cinq requêtes ajoutent `->where('verification', SourceVerification::Verified)` ; signatures inchangées ; séries et badges de série suivent par le ledger (`UpdateStreaks` lit les entrées non bonus).
- Consumes: colonnes et états de factory des Tasks 4 et 5.

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`, `app(RunUserGamification::class)->handle($user)`) —
  - une activité vérifiée de 10 000 m, une `manual()`, une `implausible()`, une `ofUnknownProvenance()` → une seule entrée `sport_activity`, `source_id` = l'activité vérifiée ;
  - mesures vérifiée le `2026-09-20 08:00:00`, `manualEntry()` le 21, `ofUnknownProvenance()` le 22 → entrées `health_measurement_day` exactement `['2026-09-20']` ;
  - activités vérifiées les 28/09 et 30/09 et `manual()` le 29/09 à 12:00 → série Sport `best_count` 1 ;
  - **scénario de la revue** : 365 mesures `manualEntry()` de type `Weight` sur une même connexion Withings du joueur (`integration_connection_id` explicite), une par jour du `2025-10-01` au `2026-09-30` à 07:00 → 0 entrée `health_measurement_day`, aucune ligne de série Santé, 0 entrée `streak_milestone`, 0 attribution `health_measurement_days_*` ni `health_streak_*`, XP totale 0 ; les mêmes 365 mesures de factory par défaut → 365 entrées (1 825 XP de base) ;
  - 100 km vérifiés + 5 000 km `manual()` → attribution `sport_distance_bronze` seule, `measured_value` `100.0` ; 10 activités vérifiées + 50 `manual()` → `sport_activity_count_bronze` seule, `measured_value` `10.0`.
- [ ] **Step 2:** FAIL. **Step 3:** ajouter le filtre aux cinq requêtes.
- [ ] **Step 4:** PASS, plus `tests/Feature/Gamification` entier vert sans modification ; commit `🔒 award synced-family xp, streaks and badges from verified rows only`.

### Task 7: Couverture d'exploration et défis sur les lignes vérifiées, Objectifs inchangés

**Files:**
- Create: `functional/goals/src/Enums/MeasuredRows.php`
- Modify: `functional/sport/src/Enums/SportType.php` (`withRealWorldRoutes()`), `functional/sport/database/Factories/SportActivityFactory.php` (`sport_type` tiré parmi `withRealWorldRoutes()`, sans quoi l'aide `createRoutedActivity()` de `RebuildCoverageTest` tirerait `VirtualRide` une fois sur huit ; état `virtual()` qui pose `VirtualRide`), `functional/exploration/src/Actions/RebuildUserCoverage.php`, `functional/goals/src/Services/Dto/MetricAggregate.php`, `functional/goals/src/Services/GoalMetricAggregator.php`, `functional/gamification/src/Services/WeeklyMetricMeter.php`
- Test: nouvelles méthodes dans `tests/Feature/Exploration/RebuildCoverageTest.php`, `tests/Feature/Goals/GoalMetricAggregatorTest.php`, `tests/Feature/Gamification/WeeklyMetricMeterTest.php`, `tests/Feature/Gamification/ResolveChallengesTest.php`

**Interfaces:**
- Produces: `SportType::withRealWorldRoutes(): list<self>` (tous les cas sauf `VirtualRide`) ; `enum MeasuredRows { case All; case VerifiedOnly; }` ; `MetricAggregate::__construct(…, ?string $recordedColumn = null, ?string $verificationColumn = null)` (`verification` pour les quatre agrégats `SportActivity`, nul pour moto et exploration) ; `GoalMetricAggregator::totalsPerPeriod(GoalMetric $metric, string $userId, array $periods, MeasuredRows $rows = MeasuredRows::All): array` (avec `VerifiedOnly` et une colonne de vérification : `where(<colonne>, SourceVerification::Verified)` ; toujours une requête) ; `total()` inchangé ; `WeeklyMetricMeter::measure()` et `history()` passent `MeasuredRows::VerifiedOnly` (signatures inchangées).
- Consumes: Tasks 4 et 6.

- [ ] **Step 1: tests qui échouent** —
  - couverture (polyline `self::POLYLINE`, 3 cellules) : sur une activité `implausible()` seule → 0 cellule ; sur une activité vérifiée `virtual()` seule → 0 ; sur une `ofUnknownProvenance()` seule → 0 ; activité vérifiée qui produit 3 cellules, passée ensuite `implausible` (`update(['verification' => SourceVerification::Implausible])`), seconde reconstruction → 0 cellule ;
  - agrégateur, W40 : 10 000 m vérifiés, 5 000 m `manual()`, 7 000 m `implausible()`, 3 000 m `ofUnknownProvenance()` → `totalsPerPeriod(SportDistance, …, [W40])` = `[25.0]`, avec `MeasuredRows::VerifiedOnly` = `[10.0]` en 1 requête ; `SportActivityCount` `VerifiedOnly` → `[1.0]` ; `SportMovingTime` `VerifiedOnly` (3 600 s vérifiée, 1 800 s manuelle) → `[1.0]` ; `MotoDistance` `VerifiedOnly` (sorties de 100 et 50 km enregistrées à temps) → `[150.0]` ; `total(SportDistance, …, 2026-09-27 22:00:00, 2026-10-04 21:59:59)` → `25.0` (les Objectifs comptent tout) ;
  - meter : activités vérifiées de 10 / 20 / 30 / 40 km datées W36–W39 et 100 km `manual()` en W38 → `history(user, SportDistance, [W36, W37, W38, W39], 48)` = `[10.0, 20.0, 30.0, 40.0]` ; `measure(user, SportDistance, W40…)` avec 10 km vérifiés + 30 km `manual()` → `10.0` ;
  - résolution : défi `sport_distance` W40 accepté, cible 28 ; 20 000 m vérifiés et 15 000 m `manual()` datés `2026-10-01 08:00:00` ; passage à `2026-10-06 22:00:00` → `Failed`, `current_value` `20.00`.
- [ ] **Step 2:** FAIL. **Step 3:** `RebuildUserCoverage` : `->where('verification', SourceVerification::Verified)->whereIn('sport_type', SportType::withRealWorldRoutes())` ; agrégateur et meter comme ci-dessus.
- [ ] **Step 4:** PASS, plus `tests/Feature/Exploration`, `tests/Feature/Goals`, `tests/Unit/Goals`, `tests/Feature/Gamification` verts sans modification ; commit `🔒 build exploration coverage and measure challenges from verified real-world activities only`.

### Task 8: Fenêtres de classement et XP vérifiée par fenêtre

**Files:**
- Create: `functional/gamification/src/Enums/LeaderboardPeriod.php`, `functional/gamification/src/Services/Dto/LeaderboardWindow.php`, `functional/gamification/src/Services/Dto/LeaderboardSettings.php`, `functional/gamification/src/Exceptions/InvalidLeaderboardConfigException.php`, `functional/gamification/src/Services/VerifiedXpTotals.php`
- Modify: `functional/gamification/src/Enums/XpRuleKey.php`, `functional/gamification/src/Services/GamificationCalendar.php`, `functional/gamification/config/gamification.php` (`'leaderboard' => ['closing_grace_hours' => 48]`)
- Test: `tests/Feature/Gamification/XpRuleKeyTest.php`, `LeaderboardWindowTest.php`, `LeaderboardSettingsTest.php`, `VerifiedXpTotalsTest.php` (nouveaux)

**Interfaces:**
- Produces: `XpRuleKey::countsTowardsLeaderboard(): bool` (vrai pour `SportActivity`, `HealthMeasurementDay`, `ExplorationDailyCells`), `XpRuleKey::leaderboardKeys(): list<string>` ; `enum LeaderboardPeriod: string` — `Week = 'week'`, `Month = 'month'` ; `final readonly class LeaderboardWindow(LeaderboardPeriod $period, string $key, CarbonImmutable $startsAt, CarbonImmutable $endsAt)` avec `closesAt(int $graceHours): CarbonImmutable` ; `GamificationCalendar::leaderboardWindowOf(LeaderboardPeriod $period, CarbonInterface $moment): LeaderboardWindow` (semaine : `weekOf()` et sa clé ; mois : premier jour local à 00:00 dans `timezone()`, clé `Y-m`, bornes converties dans `config('app.timezone')`) ; `final readonly class LeaderboardSettings(int $closingGraceHours)` avec `fromConfig(): self` (`@throws InvalidLeaderboardConfigException`, entier 0..168) ; `VerifiedXpTotals::perUser(LeaderboardWindow $window): Collection` (`@return Collection<string, int>`, identifiant d'utilisateur → somme, une requête `whereIn('rule_key', XpRuleKey::leaderboardKeys())`, `occurred_at >= startsAt`, `< endsAt`, `groupBy('user_id')`, `selectRaw` avec la colonne entourée par la grammaire, utilisateurs à 0 absents).
- Consumes: rien de nouveau.

- [ ] **Step 1: tests qui échouent** —
  - clés : data provider des 9 cas → vrai exactement pour les trois ; `leaderboardKeys()` = `['sport_activity', 'health_measurement_day', 'exploration_daily_cells']` ;
  - fenêtres : `(Week, 2026-10-01 10:00:00)` → `2026-W40`, `[2026-09-27 22:00:00, 2026-10-04 22:00:00[`, `closesAt(48)` `2026-10-06 22:00:00` ; `(Week, 2026-10-04 22:00:00)` → `2026-W41` ; `(Month, 2026-09-30 21:59:59)` → `2026-09`, `[2026-08-31 22:00:00, 2026-09-30 22:00:00[`, clôture `2026-10-02 22:00:00` ; `(Month, 2026-09-30 22:00:00)` → `2026-10`, fin `2026-10-31 23:00:00`, clôture `2026-11-02 23:00:00` ; bornes dans le fuseau `UTC` ;
  - réglages : défaut 48 ; `0` et `168` acceptés ; `-1`, `169`, `'48'` → `InvalidLeaderboardConfigException` nommant `gamification.leaderboard.closing_grace_hours` ;
  - totaux (jeu de données du §8.2 de la spec, entrées `XpEntry::factory()` à `rule_key`, `domain`, `points`, `occurred_at` explicites) : W40 → `['<A>' => 65, '<B>' => 20]`, C absent, exactement 1 requête ; septembre → A 75 seul ; octobre → A 45, B 20 ; fenêtre sans entrée → collection vide.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter (docblock du danger sur `perUser()` : « the window bounds must already be in the application timezone, a zoned Carbon would shift the period »).
- [ ] **Step 4:** PASS, plus `tests/Feature/Gamification/GamificationWeekTest.php`, `GamificationCommandsTest.php`, `XpRulesTest.php` verts ; commit `✨ define leaderboard windows and total the verified xp of a window in one grouped query`.

### Task 9: Instantanés figés à la clôture

**Files:**
- Create: `functional/gamification/database/migrations/2026_10_06_000004_create_leaderboard_snapshots_table.php`, `functional/gamification/database/migrations/2026_10_06_000005_create_leaderboard_snapshot_entries_table.php`, `functional/gamification/src/Models/LeaderboardSnapshot.php`, `functional/gamification/src/Models/LeaderboardSnapshotEntry.php`, `functional/gamification/database/Factories/LeaderboardSnapshotFactory.php`, `functional/gamification/database/Factories/LeaderboardSnapshotEntryFactory.php`, `functional/gamification/src/Actions/FreezeLeaderboardPeriods.php`, `functional/gamification/src/Console/FreezeLeaderboards.php`
- Modify: `functional/gamification/src/Providers/GamificationServiceProvider.php` (commande, planification `hourly()->withoutOverlapping()`), `functional/gamification/src/Listeners/DeleteUserGamificationData.php`
- Test: `tests/Feature/Gamification/FreezeLeaderboardPeriodsTest.php`, `tests/Feature/Gamification/LeaderboardSnapshotModelTest.php` (nouveaux)

**Interfaces:**
- Produces: tables du §8.3 de la spec (`ulid('id')->primary()`, `foreignUlid(...)->constrained(...)` sans cascade, `hasTable` dans `up()`) ; `LeaderboardSnapshot` (`HasUlids`, `HasFactory`, casts `period` `LeaderboardPeriod::class` et quatre `datetime`, `entries(): HasMany`) ; `LeaderboardSnapshotEntry` (`snapshot(): BelongsTo` sur `leaderboard_snapshot_id`, `user(): BelongsTo`, cast `verified_xp` `integer`) ; `FreezeLeaderboardPeriods::pendingWindows(CarbonInterface $moment): list<LeaderboardWindow>` (par période, dans l'ordre : de la fenêtre qui suit le dernier `ends_at` figé à la dernière fenêtre dont `closesAt(grace) <= $moment` ; sans instantané de la période, la dernière fenêtre close seule) et `freeze(LeaderboardWindow $window, CarbonInterface $moment): void` (transaction : ignore une fenêtre déjà figée, crée l'instantané avec `closes_at` et `frozen_at = $moment`, insère les entrées de `VerifiedXpTotals::perUser()` par lots de 500 avec `id` = `strtolower((string) Str::ulid())` et horodatages) ; commande `gamification:freeze-leaderboards` : `info("Freezing {$window->period->value} {$window->key}...")` avant chaque fenêtre, puis `comment("Froze {$count} leaderboard periods.")`.
- Consumes: `LeaderboardWindow`, `LeaderboardPeriod`, `GamificationCalendar::leaderboardWindowOf`, `LeaderboardSettings`, `VerifiedXpTotals` (Task 8).

- [ ] **Step 1: tests qui échouent** (jeu de données de la Task 8) —
  - `it_freezes_the_last_closed_week_and_month_on_a_first_run` : aucun instantané, `travelTo(2026-10-06 21:59:59)` → `pendingWindows` = `[week 2026-W39, month 2026-09]`, commande → `Froze 2 leaderboard periods.`, septembre porte A 75 ;
  - `travelTo(2026-10-06 22:00:00)` → W40 figée, `closes_at` `2026-10-06 22:00:00`, `frozen_at` `2026-10-06 22:00:00`, entrées A 65 et B 20, C absent ;
  - `it_keeps_a_frozen_week_when_late_xp_arrives` : après le gel de W40, `XpEntry` `sport_activity` 100 pour A à `2026-10-01 12:00:00`, `travelTo(2026-10-06 23:00:00)`, nouveau passage → 0 fenêtre, A toujours 65 ; `artisan('gamification:recalculate')` → A toujours 65 ;
  - `it_writes_nothing_on_a_second_run` : deux passages au même instant → le second rend 0, nombre de lignes inchangé ;
  - `it_catches_up_every_closed_window_in_order` : instantanés de factory W38 et août ; `travelTo(2026-10-14 00:00:00)` → `week 2026-W39`, `week 2026-W40`, `week 2026-W41`, `month 2026-09`, dans cet ordre ; sans instantané au même instant → `week 2026-W41`, `month 2026-09` seulement ;
  - `it_freezes_an_empty_window` : W41 sans XP → instantané présent, 0 entrée ;
  - grâce 72 → à `2026-10-06 22:00:00` W40 absente ; à `2026-10-07 22:00:00` figée avec `closes_at` `2026-10-07 22:00:00` ;
  - planification : l'événement de `app(Schedule::class)->events()` dont la commande contient `gamification:freeze-leaderboards` a l'expression `0 * * * *` et `withoutOverlapping` ;
  - modèle : relations ; suppression de l'utilisateur A (`$user->delete()`, événement `UserDeleting`) → ses entrées supprimées, celles de B et les instantanés intacts ; contrainte `(leaderboard_snapshot_id, user_id)` unique (second insert → `UniqueConstraintViolationException`).
- [ ] **Step 2:** FAIL. **Step 3:** implémenter ; boucle de `pendingWindows()` : dernière fenêtre close = `leaderboardWindowOf($period, $moment)` reculée par `leaderboardWindowOf($period, $window->startsAt->subSecond())` tant que `closesAt($grace) > $moment` ; fenêtre suivante = `leaderboardWindowOf($period, $window->endsAt)`.
- [ ] **Step 4:** PASS, plus `tests/Feature/Gamification` entier vert sans modification ; commit `✨ freeze the verified xp standings of each closed leaderboard week and month`.

### Task 10: Vérifications finales, env local, PR et procédure de livraison

- [ ] Merger `origin/feature/anti-cheat-hardening` (ou `origin/develop` une fois celle-ci mergée) s'il a bougé ; résoudre et relancer les tests touchés.
- [ ] `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur, aucun ajout au baseline), `php artisan test --compact` (tout vert, aucun test ignoré).
- [ ] Allumer l'env local ; donner à Quentin l'adresse, les identifiants de test et les liens directs : `/integrations` (bandeau d'erreur : se connecter à Strava depuis un second compte de test avec le compte Strava déjà relié au premier), `/sport` (activités manuelles et invraisemblables toujours listées), `/health`, `/exploration` (« Recalculer »), `/player` (XP, séries, badges) ; attendre sa validation.
- [ ] Après son accord : `gh pr create --base develop --title "🔒 Verified sources before the social phase"` (ou `--base feature/anti-cheat-hardening` si elle n'est pas encore mergée), description reprenant la procédure du §13 de la spec : requête de doublons à exécuter **avant** sur la production (étape bloquante), arrêt du worker et du planificateur, `migrate`, ligne `tinker` qui met en queue `SyncStravaActivitiesJob::dispatch($connection->id)` pour chaque connexion Strava puis `SyncWithingsMeasurementsJob::dispatch($connection->id)` pour chaque connexion Withings, relance du worker, `exploration:rebuild-coverage`, `gamification:recalculate`, relance du planificateur ; puis couper l'env (`ss -ltnp "sport = :<port>"`, tuer le parent puis l'enfant, vérifier que le port est libre).

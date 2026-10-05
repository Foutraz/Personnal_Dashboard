# Correctifs post-gamification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Purger toutes les notifications d'un utilisateur supprimé, borner le centre de notifications au notifiable connecté, transformer en 422 le 500 lomkit des chemins de relation non déclarés, saisir et afficher les sorties moto manuelles dans un fuseau d'affichage applicatif, documenter `APP_DEBUG=false` en production et monter les dépendances signalées par `composer audit`.

**Architecture:** Un listener `DeleteUserNotifications` dans `technical/notifications` sur `UserDeleting` ; `NotificationCenter` filtre par `whereMorphedTo('notifiable', …)` ; le défaut lomkit part en amont (issue + PR) et un pont minimal, le trait `ResolvesUndeclaredRelationPathsToNull` de la `Resource` OSDD de base, rend `null` pour un premier segment non déclaré afin que la règle lomkit `ResourceRelationOrNested` réponde 422 ; un service `DisplayTimezone` dans `technical/application` (clé `app.display_timezone`, défaut `Europe/Paris`) convertit la saisie `datetime-local` de `MotoDashboard` en UTC et l'affichage en heure locale ; la montée de dépendances est lancée par Quentin puis vérifiée. Spec : `docs/superpowers/specs/2026-10-05-post-gamification-followups-design.md`.

**Tech Stack:** Laravel 12.62 / PHP 8.4 (le lock, pas la v13 annoncée par l'en-tête Boost), layers OSDD `technical/notifications`, `technical/osdd`, `technical/application`, `functional/moto`, Livewire 3.8, lomkit/laravel-rest-api 2.21.0, PHPUnit 11.5, Pint, Larastan niveau 7.

## Global Constraints

- Worktree `/home/qmari/Projects/perso/.dashboard-worktrees/post-gamification-followups`, branche `fix/post-gamification-followups` (depuis `develop`) ; merger `origin/develop` s'il bouge (jamais de rebase) ; ne jamais toucher aux autres worktrees ni à l'arbre principal ; pas de serveur avant la tâche 7.
- Zones détenues par `feature/anti-cheat-hardening`, interdites ici : `functional/gamification`, `functional/goals`, les règles de validation des ressources REST moto / sport / finance / todo / exploration. Les tests peuvent appeler leurs endpoints, le code n'y change pas.
- Commits séparés d'une phrase en anglais avec gitmoji, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, push après chaque commit.
- Aucun commentaire inline ; docblock d'une phrase en anglais seulement s'il porte ce que la signature ne porte pas (type générique, `@throws`, danger nommé, contrat) ; pas de try-catch ; ULID partout ; pas de `cascadeOnDelete` ; exceptions nommées ; aucune chaîne magique ; aucune édition de `vendor/`, aucun patch Composer.
- Aucune dépendance Composer / npm nouvelle ; `composer.json` des layers inchangés (précédent : `technical/integrations` écoute `UserDeleting` sans déclarer `functional/users`).
- Tests PHPUnit en classes, `#[Test]`, `it_...`, factories, temps figé par `$this->travelTo(Carbon::parse('…', 'UTC'))`. Une seule requête lomkit par test (data provider plutôt que boucle) : `RestRequest` est un singleton et les relations calculées d'une ressource restent en cache dans l'application de test. Après chaque tâche : `vendor/bin/pint --dirty --format agent` puis le fichier de test de la tâche.
- Jalons temporels : dimanche `2026-10-04 23:30` Paris = `2026-10-04 21:30:00` UTC (CEST) ; dimanche `2026-12-06 23:30` Paris = `2026-12-06 22:30:00` UTC (CET).

---

### Task 1: Purge de toutes les notifications d'un utilisateur supprimé

**Files:**
- Create: `technical/notifications/src/Listeners/DeleteUserNotifications.php`
- Modify: `technical/notifications/src/Providers/NotificationsServiceProvider.php` (`protected array $listen = [UserDeleting::class => [DeleteUserNotifications::class]]`, `$this->loadListenEvent()` dans `boot()`, comme `IntegrationsServiceProvider`)
- Test: `tests/Feature/Notifications/UserDeletionPurgeTest.php`

**Interfaces:**
- Produces: `DeleteUserNotifications::handle(UserDeleting $event): void` — `$event->user->notifications()->cursor()->each(fn (DatabaseNotification $notification) => $notification->delete())`.
- Consumes: `Functional\Users\Events\UserDeleting`, `Notifiable::notifications()` (filtre `notifiable_type` + `notifiable_id`).

- [ ] **Step 1: tests qui échouent** — lignes seedées par `$user->notifications()->create(['id' => (string) Str::uuid(), 'type' => …, 'data' => ['title' => …]])` comme `NotificationCenterTest` :
  - utilisateur A avec trois lignes de types `TaskReminderNotification::class`, `ExpenseDueReminderNotification::class`, `BadgeAwardedNotification::class`, utilisateur B avec deux lignes ; `$userA->delete()` → `DatabaseNotification::query()->whereMorphedTo('notifiable', $userA)->count()` = `0`, pour B = `2`, `assertSoftDeleted($userA)` ;
  - ligne créée par `DatabaseNotification::query()->create([...])` avec `notifiable_type` = `Task::class` et `notifiable_id` = id de A → existe toujours après `$userA->delete()` ;
  - compteur incrémenté par `DatabaseNotification::deleted(...)` → `3` après `$userA->delete()` (la purge passe par Eloquent ligne à ligne) ;
  - `Event::fake()` puis `Event::assertListening(UserDeleting::class, DeleteUserNotifications::class)`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Notifications/UserDeletionPurgeTest.php` → FAIL (classe absente ; seules les lignes de badge sont purgées).
- [ ] **Step 3:** implémenter ; ne pas toucher à `DeleteUserGamificationData` (son bloc notifications devient redondant : suivi pour la branche gamification).
- [ ] **Step 4:** PASS, plus `tests/Feature/Notifications`, `tests/Feature/Gamification/ChallengeModelTest.php`, `tests/Feature/Todo/UserDeletionCascadeTest.php`, `tests/Feature/RecurringExpenses/UserDeletionCascadeTest.php` verts ; commit `🐛 purge every notification of a deleted user from the notifications layer`.

### Task 2: Centre de notifications borné au notifiable connecté

**Files:**
- Modify: `technical/notifications/src/Livewire/NotificationCenter.php`
- Test: `tests/Feature/Notifications/NotificationCenterTest.php` (ajouts)

**Interfaces:**
- Produces: `userNotifications(): Builder<DatabaseNotification>` = `DatabaseNotification::query()->whereMorphedTo('notifiable', Auth::guard('web')->user())` ; `markAsRead(string $id)` charge par `whereKey($id)` sur cette base ; `markAllAsRead()`, `getUnreadCountProperty()`, `getRecentProperty()` inchangés hors base de requête.
- Consumes: le garde `web` (modèle résolu par Larastan via `technical/application/config/auth.php`).

- [ ] **Step 1: tests qui échouent** — helper `seedForeignNotification(string $notifiableId, string $title): string` qui crée une `DatabaseNotification` de `notifiable_type` `Task::class` avec l'id donné :
  - utilisateur avec `Loyer dû` non lue + ligne étrangère `Intrus` non lue au même id → `assertSet('unreadCount', 1)`, `assertSee('Loyer dû')`, `assertDontSee('Intrus')`, `assertCount(1, $component->instance()->recent)` ;
  - `call('markAsRead', $foreignId)` → `read_at` de la ligne étrangère toujours `null` ;
  - deux non lues de l'utilisateur + une étrangère, `call('markAllAsRead')` → `$user->unreadNotifications()->count()` = `0`, `read_at` étranger `null` ;
  - les trois tests existants restent inchangés et verts.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Notifications/NotificationCenterTest.php` → FAIL (`unreadCount` 2, ligne étrangère marquée).
- [ ] **Step 3:** remplacer le filtre `where('notifiable_id', …)`.
- [ ] **Step 4:** PASS, plus `NotificationBellRendersTest` vert ; commit `🐛 scope the notification center to the morph type of the signed-in user`.

### Task 3: Chemins de relation non déclarés rejetés en 422

**Files:**
- Create: `technical/osdd/src/Rest/Resources/Concerns/ResolvesUndeclaredRelationPathsToNull.php`
- Modify: `technical/osdd/src/Rest/Resources/Resource.php` (`use ResolvesUndeclaredRelationPathsToNull;`)
- Test: `tests/Feature/Lomkit/UndeclaredRelationPathTest.php`

**Interfaces:**
- Produces: `trait ResolvesUndeclaredRelationPathsToNull` avec `relation(string $name): ?Relation` — `null` quand `Str::before(relation_without_pivot($name), '.')` n'est le `relation` d'aucune entrée de `$this->getRelations(app(RestRequest::class))`, sinon `parent::relation($name)` ; docblock d'une phrase portant le contrat (« Resolve a relation path to null as soon as its first segment is not declared on this resource. »).
- Consumes: `Lomkit\Rest\Relations\Relation`, `Lomkit\Rest\Http\Requests\RestRequest`, `relation_without_pivot()` ; la règle lomkit `ResourceRelationOrNested`, qui produit le 422.

- [ ] **Step 0: amont d'abord (Quentin).** Rédiger l'issue et la PR Lomkit/laravel-rest-api du §3 de la spec (reproduction `includes: [{"relation": "user.badge"}]` sur une ressource sans relation, 2.21.0 et `main`, observé 500 / attendu 422 sur `search.includes.0.relation`, correctif `$relation?->resource()` dans `Relationable::relation()` plus `'array'` sur `search` et lecture gardée de `relation` dans `SearchInclude` / `SearchAggregate`, test dans la suite du paquet) ; Quentin les dépose et confirme le pont. Sans son accord, la tâche s'arrête ici.
- [ ] **Step 1: tests qui échouent** (`actingAs($user, 'api')`, une requête par test) —
  - data provider des 17 endpoints (`badge-awards`, `badges`, `calendar-events`, `challenges`, `explored-cells`, `goals`, `investment-transactions`, `moto-rides`, `player-profiles`, `positions`, `recurring-expenses`, `sport-activities`, `streaks`, `tasks`, `trip-routes`, `users`, `xp-entries`) : `POST /api/<uri>/search` avec `['search' => ['includes' => [['relation' => 'user.badge']]]]` → 422, `assertJsonValidationErrors(['search.includes.0.relation' => 'The relation is not valid or allowed for this resource.'])` ;
  - `/api/moto-rides/search` avec `aggregates` `[['relation' => 'user.badge', 'type' => 'count']]` → 422 sur `search.aggregates.0.relation` ;
  - `/api/badge-awards/search` avec `includes` `[['relation' => 'badge', 'includes' => [['relation' => 'x.y']]]]` → 422 sur `search.includes.0.includes.0.relation` ;
  - garde-fous (déjà verts) : `badge.foo` → 422 sur `search.includes.0.relation` ; un `BadgeAward::factory()` de l'utilisateur, `includes` `[['relation' => 'badge']]` → 200 et `data.0.badge.id` = id du badge ; `aggregates` `[['relation' => 'badge', 'type' => 'count']]` → 200 et `data.0.badge_count` = `1` ;
  - `config(['app.debug' => true])`, include `user.badge` sur `moto-rides` → 422, `assertJsonMissingPath('trace')`, `assertJsonMissingPath('exception')` ;
  - `app(BadgeAwardResource::class)->relation('user.badge')` → `null`, `relation('badge.foo')` → `null`, `relation('badge')` → instance de `Lomkit\Rest\Relations\BelongsTo`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Lomkit/UndeclaredRelationPathTest.php` → FAIL (500 `Call to a member function resource() on null` sur les 17 endpoints, l'agrégat et l'include imbriqué).
- [ ] **Step 3:** implémenter le trait ; ne pas couvrir les éléments malformés (`includes: ["user"]`, `relation` absente ou non chaîne, `search: "x"`) : résiduel documenté, corrigé par la PR amont.
- [ ] **Step 4:** PASS, plus `tests/Feature/Lomkit` et tous les `tests/Feature/*/*ApiScopeTest.php` verts ; commit `🐛 reject undeclared nested relation paths with a 422 instead of a lomkit 500`, URL de l'issue amont dans la description de PR avec la version lomkit qui permettra de supprimer le trait.

### Task 4: Fuseau d'affichage applicatif et sorties moto saisies en heure locale

**Files:**
- Create: `technical/application/src/Time/DisplayTimezone.php`, `technical/application/src/Exceptions/InvalidDisplayTimezoneException.php`
- Modify: `technical/application/config/app.php` (`'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Europe/Paris')`, sans cast ni repli), `.env.example` (`APP_DISPLAY_TIMEZONE=Europe/Paris` sous `APP_URL`), `functional/moto/src/Livewire/MotoDashboard.php` (`mount`, `logRide`, `render`), `functional/moto/resources/views/moto.blade.php` (ligne de date de la liste des sorties)
- Test: `tests/Feature/Application/DisplayTimezoneTest.php`, `tests/Feature/Moto/MotoRideTimezoneTest.php`

**Interfaces:**
- Produces: `final class DisplayTimezone` (constante `INPUT_FORMAT = 'Y-m-d\TH:i'`) avec `name(): string` (`@throws InvalidDisplayTimezoneException` si la valeur n'est pas dans `timezone_identifiers_list()`), `toApplicationTime(string $localDateTime): CarbonImmutable` (parse dans le fuseau d'affichage, converti en `config('app.timezone')`), `toDisplayTime(CarbonInterface $moment): CarbonImmutable`, `inputValue(CarbonInterface $moment): string` ; `InvalidDisplayTimezoneException::forValue(mixed $configured): self` (étend `UnexpectedValueException`, message nommant `APP_DISPLAY_TIMEZONE`, `app.display_timezone` et la valeur reçue) ; `MotoDashboard::mount(DisplayTimezone $displayTimezone)`, `logRide(DisplayTimezone $displayTimezone)` (`started_at` = `toApplicationTime($this->rideStartedAt)`, champ remis à `inputValue(CarbonImmutable::now())`), `render(…, DisplayTimezone $displayTimezone)` qui passe `displayTimezone` (nom) à la vue ; vue : `$ride->started_at->copy()->setTimezone($displayTimezone)->translatedFormat('d/m/Y H\h')`.
- Consumes: `config('app.timezone')` (UTC), `ApplicationServiceProvider` (fusion de `app.php`).

- [ ] **Step 1: tests qui échouent** —
  - `config('app.display_timezone')` = `Europe/Paris` ; `name()` = `Europe/Paris` ; `config(['app.display_timezone' => 'Mars/Olympus'])` → `InvalidDisplayTimezoneException` dont le message contient `APP_DISPLAY_TIMEZONE` et `Mars/Olympus` ; `''` → même exception ;
  - `toApplicationTime('2026-10-04T23:30')` → `toDateTimeString()` `2026-10-04 21:30:00`, fuseau `UTC` ; `toApplicationTime('2026-12-06T23:30')` → `2026-12-06 22:30:00` ; `America/New_York` : `toApplicationTime('2026-10-04T23:30')` → `2026-10-05 03:30:00` ;
  - `toDisplayTime(CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'))` → `format('Y-m-d H:i')` `2026-10-04 23:30`, fuseau `Europe/Paris` ; `inputValue(CarbonImmutable::parse('2026-12-06 22:30:00', 'UTC'))` → `2026-12-06T23:30` ;
  - composant (`Config::set('weather.api_key', null)`, `travelTo(2026-10-04 21:30:00 UTC)`) : au montage `assertSet('rideStartedAt', '2026-10-04T23:30')` ; titre `Sortie dominicale`, `rideStartedAt` `2026-10-04T23:30`, durée `90`, distance `120`, `call('logRide')` → `MotoRide::query()->sole()->started_at->toDateTimeString()` = `2026-10-04 21:30:00` et `assertSet('rideStartedAt', '2026-10-04T23:30')` ; `rideStartedAt` `2026-12-06T23:30` → `2026-12-06 22:30:00` ;
  - liste : sortie de l'utilisateur à `2026-10-04 21:30:00` UTC → `assertSee('04/10/2026 23h')`, `assertDontSee('04/10/2026 21h')`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Application/DisplayTimezoneTest.php tests/Feature/Moto/MotoRideTimezoneTest.php` → FAIL (classe absente ; sortie stockée `2026-10-04 23:30:00`).
- [ ] **Step 3:** implémenter ; ne pas toucher à la validation REST de `MotoRideResource` ni à `gamification.timezone` ; bandeau météo, créneaux favorables et `TodoBoard::newDueAt` restent hors périmètre (suivis du §7 de la spec).
- [ ] **Step 4:** PASS, plus `tests/Feature/Moto` entier vert (dont `it_logs_a_ride_owned_by_the_authenticated_user` inchangé) ; commit `🐛 read and display manual moto ride times in the configured display timezone`.

### Task 5: `APP_DEBUG` en production

**Files:** aucun (le dépôt ne contient aucun fichier de configuration de production ; `.env.example` garde `APP_DEBUG=true` pour le local).

- [ ] Rappeler à Quentin, dans la description de PR, que le `.env` du serveur doit porter `APP_ENV=production` et `APP_DEBUG=false`, puis `php artisan config:cache` ; vérification côté serveur : `php artisan about --only=environment` affiche `Debug Mode … OFF`.
- [ ] Aucun commit.

### Task 6: Montée des dépendances signalées par `composer audit`

**Files:**
- Modify: `composer.lock` (seul ; les contraintes de `composer.json` autorisent déjà toutes les cibles)

- [ ] **Step 1 (Quentin, poste avec accès GitHub pour les SDK Foutraz) :**
  `cd /home/qmari/Projects/perso/.dashboard-worktrees/post-gamification-followups && composer update laravel/framework livewire/livewire league/commonmark league/flysystem guzzlehttp/guzzle phpseclib/phpseclib symfony/yaml --with-all-dependencies`
  Cibles attendues (Packagist, 2026-10-05) : laravel/framework 12.62.0 → 12.69.3, livewire/livewire 3.8.1 → 3.8.10, league/commonmark 2.8.2 → 2.10.3, league/flysystem 3.35.1 → 3.36.0, guzzlehttp/guzzle 7.12.3 → 7.15.5, phpseclib/phpseclib 3.0.55 → 3.0.57, symfony/yaml 7.4.11 → 7.4.20 (au minimum : 12.69.0, 3.8.3, 2.10.2, 3.35.3, 7.15.2, 3.0.57, 7.4.12).
- [ ] **Step 2 (implémenteur) :** `git diff --stat` ne montre que `composer.lock` ; `composer show laravel/framework livewire/livewire league/commonmark league/flysystem guzzlehttp/guzzle phpseclib/phpseclib symfony/yaml` ≥ minima ; `composer audit --locked` → `No security vulnerability advisories found.` (tout nouvel avis est rapporté, pas ignoré).
- [ ] **Step 3:** `php artisan test --compact` (tout vert), `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur, aucun ajout au baseline), `vendor/bin/pint --dirty --format agent` (aucun fichier PHP modifié).
- [ ] **Step 4:** commit `⬆️ bump the advised dependencies to their patched releases`, push. Si Quentin lance la mise à jour avant les tâches 1 à 4, relancer leurs fichiers de test.

### Task 7: Vérifications finales, env local et PR

- [ ] Merger `origin/develop` s'il a bougé ; résoudre et relancer les tests touchés.
- [ ] `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur, aucun ajout au baseline), `php artisan test --compact` (tout vert).
- [ ] Allumer l'env local et donner à Quentin l'adresse (via `get-absolute-url`), un utilisateur seedé (mot de passe de la factory `password`) et les liens directs : `/moto` (saisie d'une sortie dimanche 23:30, liste en heure de Paris), la cloche de notifications du layout, `/api/moto-rides/search` avec `includes: [{"relation": "user.badge"}]` (422) depuis Bruno ; attendre sa validation.
- [ ] Après son accord : `gh pr create --base develop --title "🐛 Post-gamification follow-up fixes"` (description : issue lomkit amont, rappel `APP_DEBUG=false`, suivis du §7 de la spec), puis couper l'env (`ss -ltnp "sport = :<port>"`, tuer le parent puis l'enfant, vérifier que le port est libre).

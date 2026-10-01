# Gamification G4 — Challenges personnels — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Proposer chaque semaine ISO (lundi 00:00 Europe/Paris) jusqu'à trois défis personnels calculés sur la médiane des quatre dernières semaines de l'utilisateur, les laisser accepter ou passer depuis le hub Joueur, suivre leur progression, les clôturer de façon déterministe et créditer l'XP des réussites dans le ledger avec une notification.

**Architecture:** La semaine de jeu (`GamificationWeek`) vient de `GamificationCalendar`, bornes converties en UTC. Les templates sont un enum `ChallengeTemplateKey` adossé à `GoalMetric` + config ; la mesure réutilise une nouvelle méthode `GoalProgressCalculator::measure()` du layer Objectifs via `WeeklyMetricMeter`. Le cycle de vie est un State pattern (`ChallengeState`, une classe par statut, `ChallengeStateFactory`), persisté par un seul point en compare-and-set (`TransitionChallenge`). `RunChallengeCycle` (proposition → résolution → reconvergence XP) s'insère dans la transaction unique de `RunUserGamification`, qui notifie dans la transaction. Le composant Livewire `ChallengeBoard` porte accepter / passer ; l'API REST reste en lecture seule. Spec : `docs/superpowers/specs/2026-10-01-gamification-g4-challenges-design.md`.

**Tech Stack:** Laravel 13 / PHP 8.4, layer OSDD `functional/gamification` (+ `functional/goals`), Livewire 3, Alpine, Tailwind v4, lomkit/laravel-rest-api + laravel-access-control, PHPUnit 12, Pint, Larastan niveau 7.

## Global Constraints

- Worktree `/home/qmari/Projects/perso/.dashboard-worktrees/gamification-phase-4`, branche `feature/gamification-phase-4` (empilée sur `feature/gamification-phase-3`) ; merger régulièrement `origin/feature/gamification-phase-3` (jamais de rebase) ; ne jamais toucher aux autres worktrees.
- Commits séparés d'une phrase en anglais avec gitmoji, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, push après chaque commit.
- Aucun commentaire inline ; docblock d'une phrase en anglais seulement s'il porte ce que la signature ne porte pas (type générique, `@throws`, danger nommé) ; pas de try-catch ; ULID partout (`foreignUlid`, ids minuscules via `newUniqueId()`) ; pas de `cascadeOnDelete` ; exceptions nommées ; statuts, templates et clés en enums avec comportement ; aucune chaîne magique.
- Toute écriture d'XP passe par le ledger dans la transaction de `RunUserGamification` ; `challenge_completed` est une clé bonus (jamais purgée par `AwardXp`, jamais jour actif d'une série).
- Notifications de défi : canal `database` seul, non `ShouldQueue`, envoyées dans la transaction.
- Bornes de semaine en UTC avant toute requête ; aucun SQL propre à SQLite (pas de `strftime`, `YEARWEEK`, `CONVERT_TZ`) ; pas de colonne `date` comparée par égalité.
- Aucune dépendance Composer / npm nouvelle (seule la dépendance interne `functional/gamification` → `functional/goals` est déclarée).
- Textes via `__('gamification::challenges.*')` / `trans_choice`, fr + en à clés identiques ; classes Tailwind littérales complètes ; a11y (libellés accessibles, `role="progressbar"`, `aria-live`).
- Tests PHPUnit en classes, `#[Test]`, `it_...`, factories avec états, helper `faker()`, temps figé par `$this->travelTo(Carbon::parse('…', 'UTC'))`. Après chaque tâche : `vendor/bin/pint --dirty --format agent` puis le fichier de test de la tâche.
- Jalons temporels utilisés partout : W40 = `2026-W40`, lundi 28/09/2026, `starts_at` `2026-09-27 22:00:00` UTC, `ends_at` `2026-10-04 22:00:00` UTC ; historique de W40 = W36 (31/08) à W39 (21/09).

---

### Task 1: Semaine de jeu ISO

**Files:**
- Create: `functional/gamification/src/Services/Dto/GamificationWeek.php`
- Modify: `functional/gamification/src/Services/GamificationCalendar.php`
- Test: `tests/Feature/Gamification/GamificationWeekTest.php`

**Interfaces:**
- Produces: `final readonly class GamificationWeek` (`CarbonImmutable $startDate` minuit local du lundi dans le fuseau de jeu, `CarbonImmutable $startsAt` et `$endsAt` dans le fuseau applicatif, `int $isoYear`, `int $isoWeek`) avec `key(): string` (`sprintf('%d-W%02d')`), `previous(int $weeks = 1): self`, `lastMoment(): CarbonImmutable` (`endsAt − 1 s`), `hasEnded(CarbonInterface $moment): bool` (`moment ≥ endsAt`), `isPastGrace(CarbonInterface $moment, int $graceHours): bool` ; `GamificationCalendar::weekOf(CarbonInterface $moment): GamificationWeek`, `currentWeek(): GamificationWeek`.
- Consumes: `GamificationCalendar::timezone()` (repli G2 sur le fuseau applicatif).

- [ ] **Step 1: tests qui échouent** —
  - `weekOf(2026-10-01 10:00:00 UTC)` → `key()` `2026-W40`, `startDate` `2026-09-28`, `startsAt` `2026-09-27 22:00:00`, `endsAt` `2026-10-04 22:00:00`, fuseau de `startsAt` = `UTC` ;
  - frontière : `2026-10-04 21:59:59` → `2026-W40` ; `2026-10-04 22:00:00` → `2026-W41` (`startDate` `2026-10-05`) ;
  - heure d'hiver : `weekOf(2026-10-25 12:00:00)` → `2026-W43`, `startsAt` `2026-10-18 22:00:00`, `endsAt` `2026-10-25 23:00:00` ;
  - semaine 53 : `weekOf(2027-01-03 12:00:00)` → `2026-W53`, `startDate` `2026-12-28` ; `weekOf(2027-01-04 12:00:00)->previous()->key()` → `2026-W53` ;
  - `previous(4)` de W40 → `2026-W36`, `startDate` `2026-08-31` ;
  - `hasEnded` faux à `2026-10-04 21:59:59`, vrai à `22:00:00` ; `isPastGrace(…, 48)` faux à `2026-10-06 21:59:59`, vrai à `2026-10-06 22:00:00` ;
  - `config(['gamification.timezone' => 'Mars/Olympus'])` → `startsAt` de W40 = `2026-09-28 00:00:00` ;
  - `travelTo(2026-10-01 10:00:00 UTC)` → `currentWeek()->key()` = `2026-W40`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Gamification/GamificationWeekTest.php` → FAIL (classe absente).
- [ ] **Step 3:** implémenter (Carbon `startOfWeek(CarbonInterface::MONDAY)` dans le fuseau de jeu, `isoWeekYear` / `isoWeek`, `setTimezone(config('app.timezone'))` sur les instants).
- [ ] **Step 4:** PASS ; commit `✨ add the iso gamification week with paris boundaries converted to the application timezone`.

### Task 2: Mesure d'une `GoalMetric` sur une période

**Files:**
- Create: `functional/goals/src/Exceptions/UnboundedGoalMetricException.php`, `functional/gamification/src/Services/WeeklyMetricMeter.php`
- Modify: `functional/goals/src/Enums/GoalMetric.php`, `functional/goals/src/Services/GoalProgressCalculator.php`, `functional/gamification/composer.json` (`"functional/goals": "*"`, puis `composer update functional/gamification --no-interaction`)
- Test: `tests/Unit/Goals/GoalProgressCalculatorTest.php` (ajouts), `tests/Feature/Gamification/WeeklyMetricMeterTest.php`

**Interfaces:**
- Produces: `GoalMetric::isPeriodBound(): bool` (vrai pour `SportDistance`, `SportElevation`, `SportActivityCount`, `SportMovingTime`, `FinanceInvestedCapital`, `MotoDistance`, `MotoRideCount`, `ExplorationCells`) ; `GoalProgressCalculator::measure(GoalMetric $metric, string $userId, ?CarbonInterface $from, ?CarbonInterface $until): float` (bornes inclusives, `@throws UnboundedGoalMetricException`) ; `currentValue(Goal)` délègue à `measure()` pour les métriques bornées ; `WeeklyMetricMeter::measure(User $user, GoalMetric $metric, CarbonInterface $startsAt, CarbonInterface $endsAt): float` (intervalle demi-ouvert, bornes converties en fuseau applicatif, `round(…, 2)`, docblock du danger « Carbon zoné formaté en heure locale ») et `measureWeek(User $user, GoalMetric $metric, GamificationWeek $week): float`.
- Consumes: `GamificationWeek` (Task 1).

- [ ] **Step 1: tests qui échouent** —
  - data provider sur les 11 cas : `isPeriodBound()` conforme à la liste, faux pour `FinancePortfolioValue`, `Manual`, `TodoCompletionRate` ;
  - `measure(SportDistance, user, 2026-09-27 22:00:00, 2026-10-04 21:59:59)` avec 10 000 m à `2026-10-04 21:30:00`, 5 000 m à `2026-10-04 22:30:00` et 7 000 m d'un autre user dans la semaine → `10.0` ;
  - `measure(MotoRideCount, …)` avec deux sorties dans la période et une hors période → `2.0` ; `measure(ExplorationCells, …)` avec 3 cellules `first_seen_at` dans la période → `3.0` ;
  - `measure(Manual, …)` → `UnboundedGoalMetricException` ;
  - meter : W40 de `GamificationCalendar` → distance `10.0`, 5 400 s de `moving_time` → `SportMovingTime` `1.5`, 10 001 m → `10.0` (arrondi) ; bornes passées en `Europe/Paris` (`2026-09-28 00:00` / `2026-10-05 00:00`) → toujours `10.0`, jamais `15.0`.
- [ ] **Step 2:** FAIL. **Step 3:** extraire les chargeurs de `GoalProgressCalculator` vers `(string $userId, ?CarbonInterface $from, ?CarbonInterface $until)` sans changer leurs requêtes.
- [ ] **Step 4:** PASS, et `php artisan test --compact tests/Unit/Goals tests/Feature/Goals` reste vert sans modification des tests existants ; commit `✨ measure a goal metric over a period and expose a weekly meter to the gamification layer`.

### Task 3: Templates, unités, réglages, clé XP bonus et traductions

**Files:**
- Create: `src/Enums/ChallengeTemplateKey.php`, `src/Enums/ChallengeUnit.php`, `src/Services/Dto/ChallengeSettings.php`, `src/Services/Dto/ChallengeTemplateSettings.php`, `src/Exceptions/MissingChallengeTemplateConfigException.php`, `src/Exceptions/InvalidChallengeConfigException.php`, `lang/fr/challenges.php`, `lang/en/challenges.php`
- Modify: `src/Enums/XpRuleKey.php`, `src/Enums/XpSourceType.php`, `config/gamification.php` (clé `challenges` du §3 de la spec), `lang/{fr,en}/dashboard.php`, `GamificationServiceProvider` (`bind(ChallengeSettings::class, fn () => ChallengeSettings::fromConfig())`)
- Test: `tests/Feature/Gamification/ChallengeTemplateKeyTest.php`, `ChallengeSettingsTest.php`, `ChallengeUnitTest.php`, ajouts dans `AwardXpTest`, `UpdateStreaksTest`, `GamificationTranslationsTest`

**Interfaces:**
- Produces: `ChallengeTemplateKey: string` (7 cas du §3) avec `metric(): GoalMetric`, `domain(): GamificationDomain`, `unit(): ChallengeUnit`, `settings(): ChallengeTemplateSettings` (`@throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException`), `label(): string`, `description(float $target): string` (`trans_choice`, cible formatée par l'unité), `static forDomain(GamificationDomain $domain): list<self>` (ordre de déclaration), `static pickFor(list<self> $eligible, int $isoWeek): ?self` (`$eligible[$isoWeek % count]`, `null` si vide) ; `ChallengeUnit: string` (`Kilometers='km'`, `Meters='m'`, `Hours='h'`, `Count='count'`) avec `format(float)` et `formatNumber(float)` (précision 1 / 0 / 1 / 0) ; `ChallengeSettings` (`historyWeeks`, `minActiveWeeks`, `stretchRatio`, `closingGraceHours`, `xpReward`, `static fromConfig(): self`) ; `ChallengeTemplateSettings` (`step`, `floor`, `cap`) ; `XpRuleKey::ChallengeCompleted = 'challenge_completed'` (`isBonus()` vrai) ; `XpSourceType::Challenge = 'challenge'`.
- Consumes: `GoalMetric::isPeriodBound()` (Task 2), `GamificationDomain`.

- [ ] **Step 1: tests qui échouent** —
  - chaque template a une métrique `isPeriodBound()` ; `forDomain(Sport)` = `[SportDistance, SportElevation, SportActivityCount, SportMovingTime]`, `forDomain(Moto)` = `[MotoDistance, MotoRideCount]`, `forDomain(Health)` = `[]` ;
  - `pickFor([SportDistance, SportActivityCount], 40)` → `SportDistance`, `41` → `SportActivityCount`, `53` → `SportActivityCount` ; `pickFor(forDomain(Sport), 43)` → `SportMovingTime` ; `pickFor([], 40)` → `null` ;
  - `SportDistance->settings()` → step `1.0`, floor `5.0`, cap `300.0` ; config du template à `null` → `MissingChallengeTemplateConfigException` portant le template ; `step => 0` → `InvalidChallengeConfigException` avec le chemin `gamification.challenges.templates.sport_distance.step` ; `cap => 2` (sous le plancher 5) → `InvalidChallengeConfigException` ;
  - `ChallengeSettings::fromConfig()` → `4`, `2`, `0.1`, `48`, `50` ; `xp_reward => 70000` et `min_active_weeks => 5` → `InvalidChallengeConfigException` ;
  - fr : `SportDistance->label()` = `Distance sportive`, `description(28.0)` = `Parcourez 28 km cette semaine.`, `SportActivityCount->description(1.0)` = `Enregistrez 1 activité sportive cette semaine.`, `description(2.0)` = `Enregistrez 2 activités sportives cette semaine.` ; en : `Cover 28 km this week.`, `Log 1 workout this week.` ;
  - unités fr : `Kilometers->format(12.5)` = `12,5 km`, `Meters->format(1000.0)` = `1 000 m` (comparaison à espaces écrasés, comme G3), `Hours->format(3.5)` = `3,5 h`, `Count->format(2.0)` = `2` ;
  - `XpRuleKey::bonusKeys()` = `['streak_milestone', 'badge_award', 'challenge_completed']` ; une entrée `challenge_completed` dans la fenêtre survit à `AwardXp::handle` fenêtré ; une entrée `challenge_completed` isolée ne crée aucune série dans `UpdateStreaks` ;
  - `lang/{fr,en}/challenges.php` n'ont pas de clé `dashboard` ; `__('gamification::dashboard.challenges_progress', ['completed' => 1, 'committed' => 2])` en en = `Challenges: 1 / 2 completed`.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter ; fichiers de langue : `templates.<key>.name|description`, `units.*`, `statuses.*`, `board.*`, `notification.proposed_title|proposed_body|completed_title|completed_body`, `errors.not_respondable` (textes du §8 de la spec) ; `dashboard.challenges_pending` (`trans_choice`) et `dashboard.challenges_progress`.
- [ ] **Step 4:** PASS (parité fr / en incluse) ; commit `✨ add the challenge templates, units, settings and bonus xp key with french and english texts`.

### Task 4: Cycle de vie en State pattern

**Files:**
- Create: `src/Enums/ChallengeStatus.php`, `src/Challenges/States/{ChallengeState,ProposedChallengeState,AcceptedChallengeState,CompletedChallengeState,FailedChallengeState,DeclinedChallengeState,ExpiredChallengeState,RefusesChallengeTransitions,ChallengeStateFactory}.php`, `src/Services/Dto/ChallengeProgress.php`, `src/Exceptions/IllegalChallengeTransitionException.php`
- Test: `tests/Unit/Gamification/ChallengeStateTest.php`, `tests/Feature/Gamification/ChallengeStatusTest.php`

**Interfaces:**
- Produces: `ChallengeStatus: string` (`Proposed`, `Accepted`, `Completed`, `Failed`, `Declined`, `Expired`) avec `label()`, `chipClass()` (littéraux : Proposed `border-hairline bg-violet-soft text-violet`, Accepted `border-hairline bg-cyan-soft text-cyan`, Completed `border-hairline bg-lime-soft text-lime`, Failed `border-hairline bg-surface-2 text-muted`, Declined et Expired `border-hairline bg-surface text-faint`), `static open(): list<self>` (non terminaux d'après la fabrique) ; `interface ChallengeState { status(); accept(); decline(); complete(); fail(); expire(); evolve(ChallengeProgress $progress); awaitsResponse(); isCommitment(); isTerminal(); }` (transitions et `evolve` renvoient `ChallengeState`) ; `final readonly class ChallengeProgress(bool $targetReached, bool $weekEnded, bool $gracePassed)` ; `ChallengeStateFactory::fromStatus(ChallengeStatus): ChallengeState` ; `IllegalChallengeTransitionException::for(ChallengeState $from, string $operation)` (étend `DomainException`).
- Consumes: rien.

- [ ] **Step 1: tests qui échouent** —
  - `Proposed` : `accept()` → `AcceptedChallengeState`, `decline()` → `DeclinedChallengeState`, `expire()` → `ExpiredChallengeState`, `complete()` et `fail()` → `IllegalChallengeTransitionException` dont le message nomme l'opération ;
  - `Accepted` : `complete()` → `Completed`, `fail()` → `Failed`, `accept()` / `decline()` / `expire()` → exception ;
  - data provider des 4 états terminaux × 6 opérations (5 transitions + `evolve`) → 24 refus ;
  - `evolve` : Proposed (atteint, semaine en cours) → reste `Proposed` ; Proposed (semaine finie) → `Expired` ; Accepted (atteint, même après la grâce) → `Completed` ; Accepted (non atteint, semaine finie, grâce non écoulée) → reste `Accepted` ; Accepted (non atteint, grâce écoulée) → `Failed` ;
  - `fromStatus($s)->status() === $s` pour les 6 cas ; `awaitsResponse()` vrai seulement pour Proposed, `isCommitment()` seulement pour Accepted, `isTerminal()` pour Completed / Failed / Declined / Expired ;
  - `ChallengeStatus::open()` = `[Proposed, Accepted]` ; libellés fr `Proposé`, `En cours`, `Réussi`, `Manqué`, `Passé`, `Expiré` ; `chipClass()` égal aux littéraux ci-dessus.
- [ ] **Step 2:** FAIL. **Step 3:** une classe finale par état, trait de refus pour les terminaux, aucune vérification `instanceof` hors fabrique.
- [ ] **Step 4:** PASS ; commit `✨ model the challenge lifecycle as a state machine with typed illegal transitions`.

### Task 5: Modèle `Challenge`, transitions persistées, purge des lignes

**Files:**
- Create: `database/migrations/2026_10_01_000003_create_challenges_table.php`, `src/Models/Challenge.php`, `database/Factories/ChallengeFactory.php`, `src/Actions/TransitionChallenge.php`, `src/Exceptions/StaleChallengeStatusException.php`
- Modify: `src/Listeners/DeleteUserGamificationData.php` (lignes `challenges`)
- Test: `tests/Feature/Gamification/ChallengeModelTest.php`, `tests/Feature/Gamification/TransitionChallengeTest.php`

**Interfaces:**
- Produces: table du §5 de la spec (unique `challenges_week_template_unique` sur `user_id`, `week_key`, `template_key` ; index `user_id`, `status`) ; `Challenge` (`HasControl`, `HasFactory`, `HasUlids`, casts enums / `decimal:2` / `datetime` / `integer`, `user(): BelongsTo`, `state(): ChallengeState`) ; `ChallengeFactory` (défaut `Proposed` sur la semaine courante, états `accepted()`, `completed()`, `failed()`, `declined()`, `expired()`, `forWeek(GamificationWeek)`, `forTemplate(ChallengeTemplateKey)`) ; `TransitionChallenge::handle(Challenge $challenge, ChallengeState $next, ?float $currentValue = null): void` (`@throws StaleChallengeStatusException`).
- Consumes: `GamificationWeek`, `ChallengeTemplateKey`, `ChallengeStatus`, `ChallengeStateFactory`.

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`) —
  - la factory persiste un défi dont l'id est un ULID minuscule, `template_key` / `domain` / `metric` / `status` castés en enums, `target_value` `28.00` pour `['target_value' => 28]` ;
  - `insertOrIgnore` d'un doublon (user, `2026-W40`, `sport_distance`) → 1 ligne ; même template en `2026-W41` → 2 lignes ;
  - `state()` d'un défi par défaut → `ProposedChallengeState` ;
  - suppression du user → ses défis supprimés, ceux d'un autre user intacts ;
  - `TransitionChallenge` : `accept` → `Accepted`, `accepted_at` = `2026-10-01 10:00:00`, `resolved_at` nul ; puis `complete` avec `30.0` → `Completed`, `resolved_at` posé, `current_value` `30.00`, `accepted_at` inchangé ; `decline` depuis Proposed → `Declined` avec `resolved_at` ;
  - compare-and-set : modèle chargé, `Challenge::query()->whereKey($id)->update(['status' => ChallengeStatus::Declined])`, puis `accept` sur le modèle périmé → `StaleChallengeStatusException`, statut en base toujours `Declined`, `accepted_at` nul.
- [ ] **Step 2:** FAIL. **Step 3:** `foreignUlid('user_id')->constrained('users')` sans cascade, `string('week_key', 8)`, `unsignedSmallInteger('xp_reward')`, `decimal(…, 14, 2)` ; `TransitionChallenge` met à jour `whereKey()->where('status', $challenge->status)`, lève si `!== 1`, puis `forceFill()->syncOriginal()`.
- [ ] **Step 4:** PASS ; commit `✨ add the challenge model with its factory, compare-and-set transitions and user purge`.

### Task 6: Calcul de cible et proposition hebdomadaire

**Files:**
- Create: `src/Challenges/ChallengeTargetCalculator.php`, `src/Services/Dto/ChallengeTarget.php`, `src/Actions/ProposeWeeklyChallenges.php`
- Test: `tests/Unit/Gamification/ChallengeTargetCalculatorTest.php`, `tests/Feature/Gamification/ProposeWeeklyChallengesTest.php`

**Interfaces:**
- Produces: `ChallengeTargetCalculator::target(ChallengeTemplateSettings $template, ChallengeSettings $settings, list<float> $weeklyValues): ?ChallengeTarget` (pur) ; `final readonly class ChallengeTarget(float $baseline, float $target)` ; `ProposeWeeklyChallenges::handle(User $user, GamificationWeek $week): Collection<int, Challenge>` (propositions créées par cet appel, ordre des domaines, vide si l'ensemble existe ou si rien n'est éligible).
- Consumes: `WeeklyMetricMeter`, `ChallengeTemplateKey::{forDomain, pickFor, settings}`, `ChallengeSettings`, `Challenge`.

- [ ] **Step 1: tests unitaires qui échouent** (`new ChallengeSettings(4, 2, 0.1, 48, 50)`) — avec (pas, plancher, plafond) :
  - (1, 5, 300) : `[10, 20, 30, 40]` → base `25.0`, cible `28.0` ; `[10, 10, 10, 10]` → `11.0` ; `[0, 0, 1, 2]` → base `0.5`, cible `5.0` ; `[400, 500, 450, 480]` → base `465.0`, cible `300.0` ; `[0, 0, 0, 12]` → `null` ;
  - (50, 100, 10000) : `[800, 1200, 1000, 0]` → base `900.0`, cible `1000.0` ;
  - (0.5, 1, 30) : `[2, 3, 4, 5]` → base `3.5`, cible `4.0` ; `[5, 5, 5, 5]` → `5.5` ;
  - (1, 1, 14) : `[1, 1, 1, 1]` → `2.0` ;
  - étirement `0.0` : `[10, 20, 30, 40]` → `25.0`.
- [ ] **Step 2: tests feature qui échouent** (`travelTo(2026-09-28 06:00:00 UTC)`) —
  - activités sport à `2026-09-02`, `09-09`, `09-16`, `09-23` 12:00 UTC de 10 000 / 20 000 / 30 000 / 40 000 m, `moving_time` 0, `total_elevation_gain` 0 → une seule ligne : `sport_distance`, domaine sport, métrique `sport_distance`, `week_key` `2026-W40`, `starts_at` `2026-09-27 22:00:00`, `ends_at` `2026-10-04 22:00:00`, base `25.00`, cible `28.00`, `xp_reward` 50, `Proposed`, `current_value` `0.00` ; la collection renvoyée la contient ;
  - rotation : mêmes activités sans celle du 02/09, `travelTo(2026-10-05 06:00:00 UTC)` (W41, historique `[20, 30, 40, 0]`) → `sport_activity_count`, base `1.00`, cible `2.00` ;
  - trois domaines : historique sport ci-dessus + sorties moto de 100 / 120 / 140 / 160 km (une par semaine W36–W39) + cellules découvertes 0 / 4 / 6 / 8 → trois lignes dans l'ordre sport, moto, exploration : `sport_distance` 28, `moto_distance` base `130.00` cible `150.00`, `exploration_cells` base `5.00` cible `10.00` ;
  - idempotence : second appel → collection vide, toujours 3 lignes, mêmes ids ;
  - ensemble figé : ensemble sport existant, ajout de l'historique moto, nouvel appel → aucune ligne moto ;
  - aucun historique → aucune ligne ; une seule semaine active → aucune ligne ; puis backfill de trois semaines et nouvel appel → l'ensemble est créé ;
  - l'historique d'un autre user ne rend pas éligible ;
  - frontière : activités 10 km en W38 et 10 km à `2026-09-27 21:30:00` (dimanche 23:30 Paris) → éligible ; la seconde à `22:30:00` (lundi 00:30 Paris) → non éligible ;
  - utilisateur sans donnée : au plus 8 requêtes (`DB::enableQueryLog`) — 1 test d'existence + 7 mesures d'historique.
- [ ] **Step 3:** implémenter : test d'existence sur `week_key`, mesure de la période entière `[W−4.startsAt, W.startsAt[` par template puis mesures hebdomadaires des seuls templates non nuls, `insertOrIgnore` avec `newUniqueId()` et horodatages, relecture par ids.
- [ ] **Step 4:** PASS ; commit `✨ propose one weekly challenge per active domain with targets derived from the user's recent median`.

### Task 7: Résolution, XP, notifications et branchement orchestrateur

**Files:**
- Create: `src/Actions/ResolveChallenges.php`, `src/Actions/ReconvergeChallengeXp.php`, `src/Actions/RunChallengeCycle.php`, `src/Services/Dto/ChallengeCycleOutcome.php`, `src/Notifications/ChallengesProposedNotification.php`, `src/Notifications/ChallengeCompletedNotification.php`
- Modify: `src/Actions/RunUserGamification.php`, `src/Listeners/DeleteUserGamificationData.php` (deux types de notification)
- Test: `tests/Feature/Gamification/ResolveChallengesTest.php`, `ChallengeNotificationsTest.php`, ajouts dans `RunUserGamificationTest`, `ProcessUserGamificationJobTest`, `ChallengeModelTest`

**Interfaces:**
- Produces: `ResolveChallenges::handle(User $user): Collection<int, Challenge>` (défis passés à `Completed` par cet appel, triés par `starts_at` puis `template_key`) ; `ReconvergeChallengeXp::handle(User $user): void` ; `RunChallengeCycle::handle(User $user): ChallengeCycleOutcome` (`GamificationWeek $week`, `Collection $proposed`, `Collection $completed`) ; `ChallengesProposedNotification(GamificationWeek $week, int $count)` et `ChallengeCompletedNotification(Challenge $challenge)` (`via` → `['database']`, `toArray` → `title`, `body` + `week_key` / `count` ou `challenge_id` / `template_key` / `xp_reward`) ; `RunUserGamification::notifyChallenges()` privée avec docblock du danger « canal mail, broadcast ou file d'attente annonçant un défi qu'un rollback supprime ».
- Consumes: `ProposeWeeklyChallenges`, `WeeklyMetricMeter`, `TransitionChallenge`, `ChallengeState::evolve`, `ChallengeSettings::closingGraceHours`, `XpRuleKey::ChallengeCompleted`, `XpSourceType::Challenge`.

- [ ] **Step 1: tests qui échouent** — défi W40 `sport_distance` accepté, cible 28 :
  - `travelTo(2026-10-03 12:00:00 UTC)`, activités 12 000 + 18 000 m dans la semaine → `Completed`, `current_value` `30.00`, `resolved_at` `2026-10-03 12:00:00` ; entrée XP `challenge_completed`, `source_type` `challenge`, `source_id` = id, 50 points, domaine sport, `occurred_at` = `resolved_at` ; collection renvoyée = ce défi ;
  - 20 000 m → reste `Accepted`, `current_value` `20.00`, aucune XP ; 27 996 m → `28.00` → `Completed` ;
  - grâce : 20 000 m, `2026-10-06 21:59:59` → `Accepted` ; `2026-10-06 22:00:00` → `Failed`, `current_value` `20.00`, `resolved_at` posé, aucune XP ;
  - synchronisation tardive : `travelTo(2026-10-05 10:00:00 UTC)`, activité de 30 km démarrée `2026-10-04 15:00:00` → `Completed` ; activité démarrée `2026-10-04 22:30:00` → non comptée, `current_value` `0.00` ;
  - proposé : `2026-10-04 21:59:59` → reste `Proposed`, `current_value` mise à jour ; `2026-10-04 22:00:00` → `Expired` avec `resolved_at` ; proposé avec 30 km → reste `Proposed`, `current_value` `30.00` ;
  - définitif : `Completed` puis activités supprimées et nouveau passage → `Completed`, `30.00`, XP conservée ; `Declined` jamais modifié ;
  - reconvergence : une entrée `challenge_completed` orpheline est supprimée ; deux passages → une seule entrée ;
  - orchestrateur : deux passages en W40 → une seule `ChallengesProposedNotification` (fr `3 nouveaux défis cette semaine` pour trois propositions, `1 nouveau défi cette semaine` pour une) ; une `ChallengeCompletedNotification` (fr `Défi réussi : Distance sportive`, corps `Vous avez atteint 28 km et gagné 50 XP.`) qui n'est pas renvoyée au passage suivant ; les notifications ne sont pas `ShouldQueue` ;
  - rollback : `RefreshPlayerProfile` simulé qui lève `QueryException` (comme `it_awards_no_badge_and_stores_no_notification_when_a_later_step_fails`) → aucune ligne `challenges`, aucune XP, aucune notification ;
  - job : passage fenêtré (`now()->subDays(3)`) puis `gamification:recalculate` → l'entrée `challenge_completed` existe toujours et `total_xp` du profil = somme du ledger ;
  - purge : suppression du user → plus aucune notification des deux types pour lui, celles d'un autre user intactes.
- [ ] **Step 2:** FAIL. **Step 3:** `RunChallengeCycle` = proposition (semaine courante) → résolution → reconvergence ; `ResolveChallenges` charge `whereIn('status', ChallengeStatus::open())`, mesure, construit `ChallengeProgress`, met à jour seule `current_value` si l'état ne change pas, sinon `TransitionChallenge` ; `RunUserGamification` appelle `RunChallengeCycle` après `notifyBadges` et avant `RefreshPlayerProfile`.
- [ ] **Step 4:** PASS, plus `RunUserGamificationTest`, `ProcessUserGamificationJobTest`, `GamificationCommandsTest` entiers verts ; commit `✨ resolve weekly challenges with ledger xp and database notifications inside the gamification run`.

### Task 8: Réponse du joueur, policy et API REST en lecture seule

**Files:**
- Create: `src/Actions/RespondToChallenge.php`, `src/Exceptions/ChallengeNotRespondableException.php`, `src/Rest/Controls/ChallengeControl.php`, `src/Rest/Policies/ChallengePolicy.php`, `src/Rest/Resource/ChallengeResource.php`, `src/Rest/Controller/ChallengesController.php`
- Modify: `routes/api.php` (`Rest::resource('challenges', ChallengesController::class)`), `GamificationServiceProvider` (`addControl`, `Gate::policy`)
- Test: `tests/Feature/Gamification/RespondToChallengeTest.php`, ajouts dans `GamificationApiScopeTest`

**Interfaces:**
- Produces: `RespondToChallenge::accept(Challenge $challenge): void` (puis `ProcessUserGamificationJob::dispatch($challenge->user_id, now()->subDay())`), `decline(Challenge $challenge): void`, `isRespondable(Challenge $challenge): bool` (`state()->awaitsResponse()` et `now() < ends_at`) ; `ChallengeNotRespondableException` (étend `ConflictHttpException`, message `__('gamification::challenges.errors.not_respondable')`) ; `ChallengePolicy` (`create` / `update` / `delete` → `false`, `respond(Model $user, Model $model): bool` → propriété) ; ressource `challenges` aux champs du §9 de la spec, tri `starts_at desc`.
- Consumes: `TransitionChallenge`, `ChallengeState`, `ProcessUserGamificationJob`, `RejectsApiCreation`.

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`, `Queue::fake()`) —
  - accepter un défi proposé W40 → `Accepted`, `accepted_at` `2026-10-01 10:00:00`, `ProcessUserGamificationJob` poussé avec le `userId` du propriétaire ; passer → `Declined`, `resolved_at` posé, rien de poussé ;
  - accepter un défi `Accepted` → `ChallengeNotRespondableException`, `getStatusCode()` 409, message fr `Ce défi ne peut plus être accepté ni refusé.` ;
  - défi encore `Proposed` à `2026-10-04 22:00:00` → 409, statut inchangé ; `isRespondable` vrai à `2026-10-01 10:00:00`, faux à `2026-10-04 22:00:00`, faux pour un `Accepted` ;
  - policy : `respond` vrai pour le propriétaire, faux pour un autre user ;
  - API : `/api/challenges/search` renvoie les 2 défis du user et pas celui d'un autre ; champs exposés exactement ceux du §9, sans `user_id` ; filtre sur `user_id` refusé (422) ; création via `mutate` → 422 et 0 ligne ; mise à jour (`status => completed`) et suppression d'un défi propre → 403, ligne inchangée ; 401 sans authentification ; `it_denies_creation_in_every_gamification_policy` couvre `Challenge`.
- [ ] **Step 2:** FAIL. **Step 3:** copier le patron `StreakControl` / `StreakPolicy` / `StreakResource` / `StreaksController`.
- [ ] **Step 4:** PASS ; commit `✨ let players accept or decline their own open challenges and expose challenges read-only through the rest api`.

### Task 9: Tableau des défis sur le hub, tuile dashboard et seeder

**Files:**
- Create: `src/Livewire/ChallengeBoard.php`, `resources/views/challenge-board.blade.php`, `resources/views/components/challenge-card.blade.php`, `src/Services/Dto/ChallengeCard.php`, `src/Services/WeeklyChallenges.php`
- Modify: `resources/views/player.blade.php` (`<livewire:gamification-challenge-board />` après les tuiles d'XP), `GamificationServiceProvider` (`Livewire::component('gamification-challenge-board', ChallengeBoard::class)`), `src/Dashboard/GamificationDashboardContribution.php`, `database/Seeders/GamificationSeeder.php`
- Test: `tests/Feature/Gamification/ChallengeBoardTest.php`, `ChallengeCardTest.php`, ajouts dans `PlayerProfilePageTest`, `GamificationDashboardContributionTest`, `GamificationSeederTest`

**Interfaces:**
- Produces: `WeeklyChallenges::forWeek(User $user, GamificationWeek $week): Collection<int, Challenge>` (ordre des domaines puis des templates) ; `ChallengeCard::fromChallenge(Challenge $challenge, bool $isRespondable): self` avec `percentage(): float` (`min(current / target × 100, 100)`), `roundedPercentage(): int`, `progressLabel(): string`, `targetLabel()`, `baselineLabel()`, `isAlreadyReached(): bool`, `statusLabel()`, `chipClass()`, `progressAccessibleLabel()` ; `ChallengeBoard` (`public string $announcement = ''`, `accept(string $challengeId, RespondToChallenge $respond): void`, `decline(string $challengeId, RespondToChallenge $respond): void`, chargement `whereBelongsTo(Auth::user())->findOrFail()`, `$this->authorize('respond', $challenge)`).
- Consumes: `RespondToChallenge`, `ChallengePolicy::respond`, `GamificationCalendar::currentWeek`, `ChallengeStatus::chipClass`, `GamificationDomain` (icône, classes).

- [ ] **Step 1: tests qui échouent** (`travelTo(2026-10-01 10:00:00 UTC)`, locale fr) —
  - carte : courant 12, cible 28 → `percentage()` ≈ 42.857, `roundedPercentage()` 43, `progressLabel()` `12 / 28 km` ; courant 30 → 100 ; `baselineLabel()` `Votre semaine type : 25 km` ; proposé à 30 km → `isAlreadyReached()` vrai ;
  - tableau : titre `Défis de la semaine`, sous-titre `Semaine du 28 sept. au 4 oct.` ; défi proposé → boutons d'`aria-label` `Relever le défi Distance sportive` et `Passer le défi Distance sportive` ; défi accepté → aucun bouton, `role="progressbar"` avec `aria-valuenow="43"`, `aria-label` `Progression du défi Distance sportive`, `aria-valuetext` `12 / 28 km` ;
  - `call('accept', $id)` → `Accepted`, région `aria-live="polite"` contenant `Défi accepté : Distance sportive`, job poussé ; `call('decline', $id)` → `Declined`, `Défi passé : Distance sportive` ;
  - identifiant d'un défi d'un autre user ou inconnu → `assertNotFound()`, statut inchangé ; défi `Accepted` → `assertStatus(409)` ;
  - `travelTo(2026-10-04 22:00:00 UTC)` avec un défi encore `Proposed` → aucun bouton rendu ;
  - section `Semaine dernière` listant un défi W39 `Réussi` ; état vide `Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de vos 4 dernières semaines d'activité.` ;
  - en : `Challenges of the week`, `Take on the Sport distance challenge` ;
  - `PlayerProfilePage` → `assertSeeLivewire('gamification-challenge-board')` ;
  - tuile : 2 proposés W40 → ligne `2 défis à relever cette semaine` ; 1 proposé → `1 défi à relever cette semaine` ; 1 réussi + 1 accepté + 1 passé → `Défis : 1 / 2 réussis` ; aucun défi → aucune ligne commençant par `Défis` ; en `2 challenges to take on this week`, `Challenges: 1 / 2 completed` ;
  - seeder : l'utilisateur du profil seedé a trois défis de la semaine courante, `Proposed`, `Accepted` et `Completed`.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter (format de date `board.week_date_format` dans les fichiers de langue, `wire:key` par défi, `wire:confirm` traduit, `wire:loading.attr="disabled"`, SVG `aria-hidden`, classes littérales).
- [ ] **Step 4:** PASS, plus `PlayerProfilePageTest` et `GamificationDashboardContributionTest` entiers verts ; commit `✨ show the weekly challenge board on the player hub and the challenge line on the dashboard tile`.

### Task 10: Vérifications finales, env local et PR

- [ ] Merger `origin/feature/gamification-phase-3` si elle a bougé ; résoudre et relancer les tests touchés.
- [ ] `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur, aucun ajout au baseline), `php artisan test --compact` (tout vert).
- [ ] Allumer l'env local, donner à Quentin l'adresse, les identifiants de test et le lien direct vers `/player` (seeder : défis proposé, accepté et réussi) ; attendre sa validation.
- [ ] Après son accord : `gh pr create --base feature/gamification-phase-3 --title "✨ Gamification phase 4: weekly personal challenges"`, puis couper l'env (`ss -ltnp "sport = :<port>"`, tuer le parent puis l'enfant, vérifier que le port est libre).

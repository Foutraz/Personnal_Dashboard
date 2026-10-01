# Gamification G3 — Badges (jalons) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un catalogue de badges multi-paliers par domaine, attribué de façon idempotente et définitive, récompensé en XP, notifié dans la cloche et exposé sur le hub Joueur, la tuile dashboard et l'API REST.

**Architecture:** Contrat `BadgeRule` tagué `gamification.badge_rules` (une famille = une règle qui sait mesurer le user). `SyncBadgeCatalogue` matérialise la table `badges` (famille × palier) depuis les règles et la config. `EvaluateBadges` attribue les badges atteints (`badge_awards`, unique user/badge), reconverge les entrées XP `badge_award` du ledger et retourne les attributions nouvelles ; `RunUserGamification` l'appelle après `UpdateStreaks` et notifie après commit. Spec : `docs/superpowers/specs/2026-10-01-gamification-g3-badges-design.md`.

**Tech Stack:** Laravel 13 / PHP 8.4, layer OSDD `functional/gamification`, Livewire 3, Tailwind v4, lomkit/laravel-rest-api, PHPUnit 12, Pint, Larastan niveau 7.

## Global Constraints

- Worktree `/home/qmari/Projects/perso/.dashboard-worktrees/gamification-phase-3`, branche `feature/gamification-phase-3` (empilée sur `feature/gamification-phase-2`) ; merger régulièrement `origin/feature/gamification-phase-2` (jamais de rebase).
- Commits d'une phrase en anglais avec gitmoji, push après chaque commit, trailer `Co-Authored-By`.
- Pas de commentaires, docstrings d'une phrase en anglais ; pas de try-catch ; ULID ; pas de `cascadeOnDelete` ; exceptions nommées ; enums avec comportement ; textes via `__('gamification::badges.*')` (fr + en).
- Tests PHPUnit en classes, `#[Test]`, `it_...`, factories, helper `faker()`.
- Les clés bonus (`streak_milestone`, `badge_award`) ne comptent jamais comme jour actif d'une série.

---

### Task 1: Spec et plan

**Files:** Create `docs/superpowers/specs/2026-10-01-gamification-g3-badges-design.md`, `docs/superpowers/plans/2026-10-01-gamification-g3-badges.md`.

- [ ] Rédiger, committer (`📝 add the gamification g3 badges design and plan`), pousser avec `git push -u origin feature/gamification-phase-3`.

### Task 2: Enum `BadgeTier`, config et traductions

**Files:**
- Create: `functional/gamification/src/Enums/BadgeTier.php`
- Create: `functional/gamification/lang/fr/badges.php`, `functional/gamification/lang/en/badges.php`
- Modify: `functional/gamification/config/gamification.php` (clé `badges`), `GamificationServiceProvider` (`loadTranslationsFrom`)
- Test: `tests/Feature/Gamification/BadgeTierTest.php`

**Interfaces:**
- Produces: `BadgeTier: string` (`Bronze='bronze'`, `Silver='silver'`, `Gold='gold'`) avec `label(): string`, `rank(): int` (1..3), `xpReward(): int` (config `gamification.badges.tier_xp`), `accent(): string` (couleur CSS hex du métal) ; config `gamification.badges.tier_xp` et `gamification.badges.thresholds.<rule_key>.<tier>`.

- [ ] **Step 1: test qui échoue** — labels traduits en `fr` (`Bronze`, `Argent`, `Or`), rangs ordonnés, XP lue en config (override `config(['gamification.badges.tier_xp.gold' => 999])` → 999).
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Gamification/BadgeTierTest.php` → FAIL (classe absente).
- [ ] **Step 3:** implémenter l'enum, la config (paliers du tableau de la spec), les fichiers de langue (`tiers`, `rules.<key>.name`, `rules.<key>.description` avec `:threshold`, `showcase.*`, `notification.*`, `dashboard.line`).
- [ ] **Step 4:** test → PASS ; commit `✨ add the badge tier enum with configurable rewards and translations`.

### Task 3: Modèles `Badge` / `BadgeAward`, migrations, factories, purge

**Files:**
- Create: migrations `2026_10_01_000001_create_badges_table.php`, `2026_10_01_000002_create_badge_awards_table.php`
- Create: `src/Models/Badge.php`, `src/Models/BadgeAward.php`, `database/Factories/BadgeFactory.php`, `database/Factories/BadgeAwardFactory.php`
- Modify: `src/Listeners/DeleteUserGamificationData.php`, `src/Models/XpEntry.php` (`BONUS_RULE_KEYS`), `src/Actions/UpdateStreaks.php`
- Test: `tests/Feature/Gamification/BadgeModelTest.php`, ajout dans `UpdateStreaksTest`

**Interfaces:**
- Produces: `Badge` (`key`, `rule_key`, `domain: GamificationDomain`, `tier: BadgeTier`, `threshold: string`, `xp_reward: int`, `awards(): HasMany`, `name(): string`, `description(): string`) ; `BadgeAward` (`user_id`, `badge_id`, `awarded_at`, `badge(): BelongsTo`, `user(): BelongsTo`, constantes `XP_RULE_KEY = 'badge_award'`, `XP_SOURCE_TYPE = 'badge'`) ; `XpEntry::BONUS_RULE_KEYS`.

- [ ] **Step 1: tests qui échouent** — factory persiste un badge et une attribution ; clé `badges.key` unique ; doublon (user, badge) ignoré par `insertOrIgnore` ; nom/description traduits ; suppression du user → plus aucune attribution, catalogue intact ; `UpdateStreaks` ignore une entrée `badge_award` isolée (aucune série créée).
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** `php artisan make:model` n'est pas utilisable dans un layer : créer les fichiers en suivant `Streak`/`StreakFactory` ; FK `foreignUlid(...)->constrained()` sans cascade ; unique (`user_id`, `badge_id`) ; `UpdateStreaks` filtre `whereNotIn('rule_key', XpEntry::BONUS_RULE_KEYS)`.
- [ ] **Step 4:** PASS ; commit `✨ add the badge catalogue and award models with factories and user purge`.

### Task 4: Contrat `BadgeRule` et règles taguées

**Files:**
- Create: `src/Contracts/BadgeRule.php`, `src/Badges/Rules/{SportDistance,SportActivityCount,HealthMeasurementDays,FinanceInvestedCapital,MotoDistance,TodoTasksCompleted,ExplorationCells}BadgeRule.php`, `src/Badges/Rules/StreakBadgeRule.php` (abstraite) + `{Sport,Health,Todo}StreakBadgeRule.php`
- Modify: `GamificationServiceProvider` (tag `gamification.badge_rules`)
- Test: `tests/Feature/Gamification/BadgeRulesTest.php`

**Interfaces:**
- Produces: `interface BadgeRule { key(): string; domain(): GamificationDomain; unit(): string; measure(User $user): float; }` — mesure agrégée en SQL, scoped au user.

- [ ] **Step 1: tests qui échouent** — une mesure par règle avec données du user et d'un autre user (isolation) : km sport (10 000 m + 5 000 m → 15.0), nombre d'activités, jours de mesure distincts, capital net investi (via `CapitalCalculator::netInvested`), km moto, tâches terminées (les non terminées ignorées), cellules, meilleure série par domaine ; toutes les règles taguées ont des seuils configurés pour les trois paliers.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter. **Step 4:** PASS ; commit `✨ add the tagged badge rules measuring each domain milestone`.

### Task 5: `SyncBadgeCatalogue`

**Files:** Create `src/Actions/SyncBadgeCatalogue.php` ; Modify `database/Seeders/GamificationSeeder.php` ; Test `tests/Feature/Gamification/SyncBadgeCatalogueTest.php`.

**Interfaces:** `SyncBadgeCatalogue::handle(): void` — upsert par `key` (`<rule_key>_<tier>`) de `rule_key`, `domain`, `tier`, `threshold`, `xp_reward`.

- [ ] **Step 1: tests qui échouent** — 30 badges créés ; second appel idempotent (mêmes ids) ; changement de seuil en config propagé ; exception nommée `MissingBadgeThresholdException` si une règle taguée n'a pas de seuil pour un palier.
- [ ] **Steps 2-4:** FAIL → implémenter → PASS ; commit `✨ sync the badge catalogue from the tagged rules and config`.

### Task 6: `EvaluateBadges` et branchement orchestrateur

**Files:** Create `src/Actions/EvaluateBadges.php`, `src/Notifications/BadgeAwardedNotification.php` ; Modify `src/Actions/RunUserGamification.php` ; Test `tests/Feature/Gamification/EvaluateBadgesTest.php`, ajout dans `ProcessUserGamificationJobTest`.

**Interfaces:**
- `EvaluateBadges::handle(User $user): Collection<int, BadgeAward>` (attributions nouvelles ; ledger reconvergé).
- `BadgeAwardedNotification(Badge $badge)` : `via` → `['database']`, `toArray` → `title`, `badge_key`, `tier`, `xp_reward`.
- `RunUserGamification` : appelle `EvaluateBadges` après `UpdateStreaks`, rafraîchit le profil, notifie après commit.

- [ ] **Step 1: tests qui échouent** — seuil atteint → attribution + entrée XP `badge_award` (points = `xp_reward`, domaine du badge) ; second passage : ni doublon d'attribution, ni doublon d'XP, ni seconde notification ; donnée supprimée → badge conservé ; passage complet du job (purge de fenêtre `AwardXp`) → XP de badge toujours présente et total du profil = somme du ledger ; notification database stockée avec le titre traduit ; user sans données → aucune attribution.
- [ ] **Steps 2-4:** FAIL → implémenter → PASS ; commit `✨ award badges idempotently with ledger xp and notifications`.

### Task 7: API REST lecture seule

**Files:** Create `src/Rest/{Resource,Controller,Controls,Policies}/Badge*` et `BadgeAward*` ; Modify `routes/api.php`, `GamificationServiceProvider` ; Test ajout dans `GamificationApiScopeTest`.

- [ ] **Step 1: tests qui échouent** — `/api/badges/search` liste le catalogue ; `/api/badge-awards/search` ne renvoie que les attributions du user (relation `badge` incluable) ; création / mise à jour / suppression refusées ; 401 sans auth.
- [ ] **Steps 2-4:** FAIL → implémenter → PASS ; commit `✨ expose read-only badges and badge awards through the rest api`.

### Task 8: Vitrine hub + tuile dashboard

**Files:** Create `src/Services/BadgeShowcase.php`, `src/Services/Dto/BadgeFamilyProgress.php` ; Modify `src/Livewire/PlayerProfilePage.php`, `resources/views/player.blade.php`, `src/Dashboard/GamificationDashboardContribution.php` ; Test `tests/Feature/Gamification/BadgeShowcaseTest.php`, ajouts dans `PlayerProfilePageTest`, `GamificationDashboardContributionTest`.

**Interfaces:** `BadgeShowcase::families(User): Collection<int, BadgeFamilyProgress>` (rule key, nom, domaine, badges des trois paliers avec état obtenu, valeur courante, prochain seuil, pourcentage) ; `BadgeShowcase::earnedCount(User): int`, `totalCount(): int`.

- [ ] **Step 1: tests qui échouent** — progression vers le prochain palier mesurée depuis le palier précédent (150 km → (150-100)/(1000-100) ≈ 5,6 % vers Argent) ; famille complète → 100 % ; page affiche « Badges », compteur `1 / 30`, nom traduit ; tuile contient `Badges : 1 / 30`.
- [ ] **Steps 2-4:** FAIL → implémenter → PASS ; commit `✨ showcase badges on the player hub and dashboard tile`.

### Task 9: Vérifications finales et PR

- [ ] Merger `origin/feature/gamification-phase-2` si elle a bougé ; résoudre et relancer.
- [ ] `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G` (0 erreur), `php artisan test --compact` (tout vert).
- [ ] `gh pr create --base feature/gamification-phase-2 --title "✨ Gamification phase 3: badges catalogue, awards and showcase"`.

# Gamification du Personnal Dashboard — design & roadmap

> Date : 2026-07-03. Statut : en attente de validation utilisateur.
> Objectif produit : transformer l'application en expérience de jeu (challenges) tout en gardant l'intérêt pratique et le suivi comme priorité absolue.

## 1. Contexte

Un socle de gamification existe déjà sur la branche `feature/gamification-phase-1` (non mergée dans `develop`) : module `functional/gamification` avec ledger d'XP idempotent (`XpEntry`), profil joueur avec niveaux (`PlayerProfile`, `LevelCurve`), règles d'XP par domaine taguées `gamification.xp_rules` (sport, santé, finance, moto, todo, exploration), événements de fin de sync dans les modules sources, job `ProcessUserGamificationJob`, commandes de recalcul/backfill schedulées, page Livewire `PlayerProfilePage`, contribution dashboard et API REST en lecture seule.

Il manque : streaks, badges, challenges, et toute dimension multi-utilisateurs (l'app scope strictement chaque donnée par `user_id` via les policies Lomkit).

## 2. Hypothèses prises (à valider par l'utilisateur)

1. **On bâtit sur le socle existant** `feature/gamification-phase-1` plutôt que de repartir de zéro.
2. « Challenger les utilisateurs » couvre d'abord des **challenges personnels** (se dépasser soi-même), puis dans un second temps des **défis entre utilisateurs et un leaderboard opt-in**.
3. La gamification reste **non intrusive** : elle enrichit le dashboard et les pages existantes, elle ne remplace aucun écran de suivi.
4. Aucune dépendance Composer/npm nouvelle n'est nécessaire (tout se fait avec Livewire, Tailwind, ApexCharts déjà présents).

## 3. Approches envisagées

### Approche A — Couche méta légère (XP + badges + streaks, sans challenges)
Simple et rapide, mais ne répond pas au but énoncé (« challenger les utilisateurs ») : rien ne pousse à l'action, on ne fait que décorer l'historique.

### Approche B — Challenges par-dessus le socle XP, social ensuite (RECOMMANDÉE)
On consolide le moteur XP existant, puis on empile trois mécaniques : streaks (régularité), badges (jalons), challenges personnels auto-générés à partir de l'historique de l'utilisateur (« bats tes 42 km de la semaine dernière »). La dimension sociale (leaderboard, défis entre utilisateurs) arrive en dernière phase, en opt-in explicite, car elle exige d'ouvrir prudemment l'isolation stricte des données.
Avantages : valeur incrémentale à chaque phase, chaque mécanique réutilise le pattern « règles taguées + attribution idempotente » déjà en place, risque privacy repoussé en fin de roadmap.

### Approche C — Full social d'emblée (leaderboard + PvP dès la phase 1)
Répond littéralement à « challenger les utilisateurs » mais impose de casser immédiatement le scoping par user (policies Lomkit, API REST), le plus gros risque du projet, avant même que les mécaniques de base existent. Rejetée.

**Décision : Approche B.**

## 4. Design retenu

### 4.1 Architecture
Tout vit dans le module existant `functional/gamification` (pattern layer OSDD identique aux autres modules). Chaque mécanique suit le pattern du socle : un contrat d'évaluation (`XpRule` aujourd'hui, `BadgeRule` / `StreakSource` / `ChallengeTemplate` demain), des implémentations taguées dans le container, une attribution idempotente rejouable sur fenêtre glissante, déclenchée par les événements de fin de sync existants et par le recalcul schedulé quotidien.

### 4.2 Modèles (tous en ULID, SoftDeletes selon convention, cascade sur `UserDeleting`)
- `Streak` : user_id, domain (`GamificationDomain`), current_count, best_count, last_activity_date. Un streak = jours consécutifs avec au moins une donnée dans le domaine.
- `Badge` (catalogue, seedé) : key, domain, tier (Bronze/Silver/Gold — enum), threshold, description ; `BadgeAward` : user_id, badge_id, awarded_at, unique (user, badge).
- `Challenge` : user_id, template_key, domain, metric (réutilise `GoalMetric`), target_value, baseline_value, starts_at, ends_at, status (Proposed/Accepted/Completed/Failed/Declined — enum), xp_reward ; généré chaque lundi par template à partir de l'historique du user.
- Phase sociale : `PlayerProfile.is_public` (opt-in), `ChallengeInvitation` (challenger_id, challenged_id, challenge partagé), leaderboard calculé (pas de table dédiée).

### 4.3 Attribution & idempotence
Le `ProcessUserGamificationJob` existant devient l'orchestrateur : après le calcul XP, il met à jour streaks, évalue les badges, met à jour la progression des challenges actifs. Chaque écriture est idempotente (upsert par clé naturelle), comme le ledger XP actuel. La complétion d'un challenge ou l'obtention d'un badge crédite l'XP via `AwardXp` (source_key dédié) et notifie via le système de notifications database existant.

### 4.4 UI (Livewire + composants ui existants)
- La page `PlayerProfilePage` devient le hub « Jeu » : anneau de niveau (`level-ring`), streaks par domaine, vitrine de badges, challenges de la semaine (accepter/refuser, progression).
- Le dashboard d'accueil garde une seule tuile gamification (résumé : niveau, streak le plus chaud, challenge en cours) via la contribution existante.
- Les notifications (level up, badge, challenge complété) passent par le `NotificationCenter` existant.

### 4.5 Gestion des erreurs et des cas limites
- Pas de try-catch (règle projet) : les jobs gardent `tries`/`backoff`/`WithoutOverlapping`.
- Données arrivant en retard (sync tardive) : couvertes par la fenêtre glissante de recalcul quotidien, qui peut réparer un streak cassé à tort dans la fenêtre.
- Utilisateur sans intégration dans un domaine : aucune règle de ce domaine ne produit de proposition de challenge ni de streak (silencieux, pas d'état vide anxiogène).
- Suppression utilisateur : listener de purge sur `UserDeleting`, comme les autres modules.

### 4.6 Tests
Mêmes patterns que le reste du repo : tests Feature par module (`*DashboardContributionTest`, `*ApiScopeTest`, `UserDeletionCascadeTest`, tests de jobs et de composants Livewire), factories avec states, TDD par tâche. L'idempotence (rejouer un job ne double pas les récompenses) est testée systématiquement.

## 5. Roadmap

Chaque phase = cycle SDD complet : spec courte → plan → branche/worktree dédié → TDD → Pint + Larastan → PR vers `develop` → revue → merge avant la phase suivante.

### Phase G1 — Consolider et merger le socle XP (existant)
Relire `feature/gamification-phase-1`, la rebaser sur `develop`, revue de code complète, correctifs éventuels, PR et merge. Livrable : XP + niveaux + page profil en production sur `develop`.

### Phase G2 — Streaks (régularité)
Modèle `Streak`, calcul par domaine dans l'orchestrateur, XP bonus de palier (7/30/100 jours), affichage sur le hub Jeu et la tuile dashboard. Livrable : séries visibles et récompensées.

### Phase G3 — Badges (jalons)
Catalogue seedé multi-tiers par domaine (distance cumulée, nombre d'activités, régularité, patrimoine investi…), contrat `BadgeRule` tagué, attribution idempotente, notifications, vitrine sur le hub. Livrable : collection de badges.

### Phase G4 — Challenges personnels (cœur du « jeu »)
Templates de challenges auto-générés chaque semaine à partir de l'historique (`GoalMetric` réutilisé), cycle Proposed → Accepted → Completed/Failed, récompense XP, UI d'acceptation et de suivi, notifications. Livrable : l'app propose activement des défis pratiques et personnalisés.

### Phase G5 — Social opt-in (challenger les autres)
Profil public opt-in, leaderboard (XP hebdo/mensuel, streaks) limité aux profils publics, invitations de challenge entre utilisateurs sur métrique commune, adaptation ciblée des policies Lomkit + tests d'isolation renforcés. Livrable : compétition entre utilisateurs consentants.

### Phase G6 — Équilibrage & polish
Réglage des barèmes XP (config `gamification.php`), digest hebdo de notifications, graphique de progression d'XP (ApexCharts), revue design du hub avec le skill frontend-design. Livrable : expérience cohérente et équilibrée.

## 6. Points ouverts (à trancher à la validation)

1. Confirmer les hypothèses de la section 2 (surtout la place du multi-utilisateurs : G5 peut être avancée si c'est le cœur du besoin).
2. Nom de branche souhaité pour chaque phase (convention actuelle : `feature/gamification-phase-N`).
3. G5 introduit une surface privacy nouvelle : valider le principe opt-in avant tout développement de cette phase.

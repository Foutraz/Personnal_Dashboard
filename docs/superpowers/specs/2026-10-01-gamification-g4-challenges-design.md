# Gamification G4 — Challenges personnels — design

> Date : 2026-10-01. Phase G4 de la roadmap `2026-07-03-gamification-roadmap-design.md` (section 5).
> Base : `feature/gamification-phase-3` (badges, elle-même empilée sur G2). Branche : `feature/gamification-phase-4`.

## 1. Objectif

Faire de l'application un **adversaire bienveillant** : chaque semaine, le hub Joueur propose quelques défis
pratiques et personnalisés, calculés sur l'historique réel de l'utilisateur (« parcourez 28 km cette semaine,
votre semaine type est à 25 km »). Le joueur accepte ou passe chaque défi, suit sa progression pendant la
semaine, et gagne de l'XP s'il le réussit. Les défis réutilisent les métriques des Objectifs (`GoalMetric`) pour
que « 28 km » signifie exactement la même chose sur les deux écrans.

## 2. Périmètre

Inclus :

- Sept templates de défis hebdomadaires (enum `ChallengeTemplateKey` + config `gamification.challenges`), tous
  adossés à une `GoalMetric` bornée par une période (sport, moto, exploration).
- Une semaine de jeu explicite : semaine ISO, du lundi 00:00 au lundi suivant 00:00 (exclu), dans le fuseau
  `gamification.timezone` (défaut `Europe/Paris`), portée par `GamificationCalendar`.
- Une nouvelle méthode publique `GoalProgressCalculator::measure()` dans le layer `functional/goals`, réutilisée par
  la gamification pour mesurer une métrique sur une période.
- Table `challenges` (ULID, FK `user_id` sans cascade), un ensemble de propositions par utilisateur et par semaine,
  figé dès sa création.
- Cycle de vie en **State pattern** : `Proposed → Accepted → Completed | Failed`, `Proposed → Declined | Expired`.
- Génération, suivi et clôture dans l'orchestrateur existant `RunUserGamification` (même transaction).
- XP de défi dans le ledger (nouvelle clé bonus `challenge_completed`), reconvergée à chaque passage.
- Notifications database `ChallengesProposedNotification` et `ChallengeCompletedNotification`.
- Composant Livewire `ChallengeBoard` sur le hub Joueur (accepter / passer, progression, semaine précédente),
  ligne « défis » sur la tuile dashboard.
- API REST Lomkit `challenges` en **lecture seule**, scopée au propriétaire.
- Purge des défis et de leurs notifications sur `UserDeleting` via `DeleteUserGamificationData`.

Exclus : défis entre utilisateurs et leaderboard (G5), choix ou paramétrage des défis par l'utilisateur, défis
multi-semaines, badges « défis réussis » et digest de notifications (G6), défis Santé / Tâches / Finance
(voir décision 4), acceptation via l'API REST.

## 3. Templates

| Template (`template_key`) | Domaine | `GoalMetric` | Unité | Pas | Plancher | Plafond |
|---|---|---|---|---|---|---|
| `sport_distance` | Sport | `SportDistance` | km | 1 | 5 | 300 |
| `sport_elevation` | Sport | `SportElevation` | m | 50 | 100 | 10 000 |
| `sport_activity_count` | Sport | `SportActivityCount` | activités | 1 | 1 | 14 |
| `sport_moving_time` | Sport | `SportMovingTime` | h | 0,5 | 1 | 30 |
| `moto_distance` | Moto | `MotoDistance` | km | 10 | 50 | 3 000 |
| `moto_ride_count` | Moto | `MotoRideCount` | sorties | 1 | 1 | 14 |
| `exploration_cells` | Exploration | `ExplorationCells` | cellules | 5 | 10 | 1 000 |

Réglages globaux (config `gamification.challenges`) : `history_weeks` = 4, `min_active_weeks` = 2,
`stretch_ratio` = 0,10, `closing_grace_hours` = 48, `xp_reward` = 50. Chaque valeur est validée à la lecture
(exceptions nommées `InvalidChallengeConfigException`, `MissingChallengeTemplateConfigException`) ;
`xp_reward` est borné à 65 535 car `xp_entries.points` est un `unsignedSmallInteger`.

## 4. Semaine de jeu et calcul de la cible

### 4.1 Semaine

`GamificationCalendar::weekOf(moment)` renvoie un `GamificationWeek` : date locale du lundi, instants `startsAt`
et `endsAt` (intervalle demi-ouvert) **convertis dans le fuseau applicatif (UTC)**, année et numéro ISO, clé
`2026-W40`. Exemple : la semaine 2026-W40 va du lundi 28/09 00:00 Paris (`2026-09-27 22:00:00` UTC) au lundi
05/10 00:00 Paris (`2026-10-04 22:00:00` UTC) ; la semaine 2026-W43 traverse le passage à l'heure d'hiver et
finit à `2026-10-25 23:00:00` UTC (169 h). La semaine 53 existe (2026-W53 : 28/12/2026 → 03/01/2027).

Danger nommé : un Carbon dans le fuseau Paris passé à une requête Eloquent est formaté en heure locale, ce qui
décalerait la semaine de l'offset (1 ou 2 h). Toutes les bornes de requête passent donc par le fuseau applicatif.

### 4.2 Mesure

`GoalProgressCalculator::measure(GoalMetric, userId, from, until)` (bornes inclusives, comme les Objectifs)
calcule une métrique bornée par une période ; `currentValue(Goal)` délègue désormais à cette méthode pour ces
métriques, sans changer son comportement. Les métriques non bornées (`Manual`, `FinancePortfolioValue`,
`TodoCompletionRate`) lèvent `UnboundedGoalMetricException`. Côté gamification, `WeeklyMetricMeter` mesure
l'intervalle `[startsAt, endsAt − 1 s]` et arrondit à deux décimales (précision de stockage).

### 4.3 Cible

Pour un template et la semaine W, l'historique est la liste des valeurs hebdomadaires des 4 semaines complètes
précédentes (W−4 à W−1, zéros compris) :

1. **Éligibilité** : au moins 2 semaines sur 4 avec une valeur > 0. Sinon, aucun défi de ce template.
2. **Base** (`baseline_value`) : médiane des 4 valeurs (moyenne des deux valeurs centrales), arrondie à 2 décimales.
3. **Étirement** : `base × (1 + 0,10)`, arrondi à 6 décimales pour neutraliser le bruit flottant.
4. **Pas** : arrondi au multiple supérieur du pas du template (quotient arrondi à 6 décimales avant le plafond
   entier, pour que 10 × 1,1 donne 11 et non 12).
5. **Bornes** : `min(max(valeur, plancher), plafond)` → `target_value`.

Exemples exacts : [10, 20, 30, 40] km → base 25, cible 28 ; [10, 10, 10, 10] km → 11 ; [0, 0, 1, 2] km → base 0,5,
cible 5 (plancher) ; [400, 500, 450, 480] km → base 465, cible 300 (plafond) ; [800, 1 200, 1 000, 0] m → base
900, cible 1 000 ; [2, 3, 4, 5] h → base 3,5, cible 4 ; [5, 5, 5, 5] h → 5,5 ; [1, 1, 1, 1] activités → 2 ;
[0, 0, 0, 12] → non éligible.

### 4.4 Sélection

Un défi au plus **par domaine** ayant au moins un template éligible, donc trois au plus par semaine (Sport,
Moto, Exploration). Dans un domaine, le template retenu est celui d'indice `numéro ISO de W mod nombre
d'éligibles` parmi les éligibles pris dans l'ordre de déclaration de l'enum : en W40 avec `sport_distance` et
`sport_activity_count` éligibles, c'est `sport_distance` ; en W41, `sport_activity_count`. La rotation est
déterministe, varie d'une semaine à l'autre et ne dépend d'aucun aléa.

## 5. Modèle de données

`challenges` :

| Colonne | Type | Rôle |
|---|---|---|
| `id` | ULID, PK | |
| `user_id` | `foreignUlid` → `users`, sans cascade | propriétaire |
| `week_key` | string(8) | semaine ISO, `2026-W40` |
| `template_key` | string(64), cast `ChallengeTemplateKey` | template |
| `domain` | string(32), cast `GamificationDomain` | dénormalisé (ledger, API) |
| `metric` | string(64), cast `GoalMetric` | dénormalisé (roadmap) |
| `starts_at`, `ends_at` | timestamp | bornes UTC de la semaine (`ends_at` exclu) |
| `baseline_value` | decimal(14,2) | médiane de l'historique |
| `target_value` | decimal(14,2) | cible figée à la proposition |
| `current_value` | decimal(14,2), défaut 0 | dernière mesure, figée à la clôture |
| `xp_reward` | unsignedSmallInteger | récompense figée à la proposition |
| `status` | string(16), cast `ChallengeStatus` | cycle de vie |
| `accepted_at` | timestamp nullable | horodatage de l'acceptation |
| `resolved_at` | timestamp nullable | horodatage de l'état terminal |
| `created_at`, `updated_at` | timestamps | `updated_at` affiché comme « mise à jour » de la progression |

Index : unique (`user_id`, `week_key`, `template_key`) ; index (`user_id`, `status`). Pas de SoftDeletes
(convention des modèles gamification), pas d'enum SQL, aucun SQL propre à SQLite. Pas de table de templates :
les templates sont du code + de la config, aucune donnée partagée n'est donc à synchroniser hors transaction.

## 6. Cycle de vie

| État | Transitions | Attend une réponse | Engagement | Terminal |
|---|---|---|---|---|
| `Proposed` | `accept` → `Accepted`, `decline` → `Declined`, `expire` → `Expired` | oui | non | non |
| `Accepted` | `complete` → `Completed`, `fail` → `Failed` | non | oui | non |
| `Completed`, `Failed`, `Declined`, `Expired` | aucune | non | non | oui |

Implémentation State (skill `design-patterns:state`) : interface `ChallengeState` (`status()`, les cinq
transitions, `evolve(ChallengeProgress)`, `awaitsResponse()`, `isCommitment()`, `isTerminal()`), une classe
finale par état, un trait commun aux états terminaux qui refuse tout, `ChallengeStateFactory` qui reconstruit
l'état depuis la colonne, `IllegalChallengeTransitionException` (exception de domaine, rapportée). Le modèle
expose seulement `state()`. `evolve()` porte la règle temporelle de chaque état : `Proposed` expire quand la
semaine est finie ; `Accepted` se termine en `Completed` dès que la cible est atteinte, en `Failed` quand le
délai de grâce est écoulé sans l'atteindre, sinon reste inchangé.

La persistance d'une transition passe par un seul point, `TransitionChallenge` : mise à jour conditionnelle
(`where status = <état lu>`, compare-and-set) qui pose `status`, `accepted_at` (vers un état d'engagement),
`resolved_at` (vers un état terminal) et, le cas échéant, `current_value` ; zéro ligne touchée lève
`StaleChallengeStatusException` (exception de domaine, rapportée).

## 7. Flux

### 7.1 Orchestrateur

`RunUserGamification` (une transaction) : `AwardXp` → `UpdateStreaks` → `EvaluateBadges` (+ notifications) →
**`RunChallengeCycle`** (+ notifications) → `RefreshPlayerProfile`. Rien ne change dans le job, le scheduler ni la
commande `gamification:recalculate`, qui héritent du nouveau maillon.

`RunChallengeCycle::handle(User)` :

1. `ProposeWeeklyChallenges` pour la semaine courante ;
2. `ResolveChallenges` (progression et transitions automatiques) ;
3. `ReconvergeChallengeXp` (ledger) ;
4. renvoie un `ChallengeCycleOutcome` (semaine, défis proposés, défis réussis) pour les notifications.

### 7.2 Génération hebdomadaire

Paresseuse et idempotente, à chaque passage de l'orchestrateur (synchronisations et passage quotidien de 02:00) :
si une ligne existe déjà pour (user, `week_key` courante), rien n'est fait — l'ensemble est **figé**. Sinon, pour
chaque template, une mesure sur les 4 semaines d'historique d'un seul tenant écarte les templates sans aucune
donnée (une requête par template pour un utilisateur sans données), puis les 4 valeurs hebdomadaires des
templates restants alimentent le calcul de cible. Les lignes sont insérées par `insertOrIgnore` (la clé unique
garantit l'absence de doublon même en cas de course) avec `status = Proposed`, `xp_reward` lu en config. Un
ensemble vide n'est pas persisté : un utilisateur qui connecte Strava le mercredi (backfill de l'historique)
reçoit ses défis au passage suivant, pour la semaine en cours. Jamais de génération rétroactive pour une semaine
passée.

### 7.3 Acceptation et refus

Actions Livewire `accept(string $challengeId)` / `decline(string $challengeId)` du composant `ChallengeBoard` :

1. chargement scopé `Challenge::query()->whereBelongsTo(<user authentifié>)->findOrFail($challengeId)` (404 pour
   l'identifiant d'un autre) — aucun identifiant d'utilisateur ne vient du client ;
2. `$this->authorize('respond', $challenge)` (`ChallengePolicy::respond`, contrôle de propriété) ;
3. `RespondToChallenge` : refuse par `ChallengeNotRespondableException` (étend `ConflictHttpException`, 409,
   message traduit) si l'état n'attend pas de réponse ou si la semaine est finie, puis applique
   `state()->accept()` ou `state()->decline()` via `TransitionChallenge` ;
4. après une acceptation, `ProcessUserGamificationJob::dispatch($user->id, now()->subDay())` pour qu'une cible
   déjà atteinte soit validée sans attendre la nuit ;
5. message d'état annoncé dans une région `aria-live`.

### 7.4 Suivi

`ResolveChallenges` mesure chaque défi ouvert (`Proposed`, `Accepted`) sur ses propres `starts_at` / `ends_at`,
arrondit à deux décimales, puis demande `state()->evolve()` : si l'état ne change pas, seule `current_value` est
mise à jour (sans toucher à `status`, ce qui évite d'écraser une acceptation concurrente) ; sinon la transition
passe par `TransitionChallenge` avec la valeur figée. La progression est mesurée **sur toute la semaine**, quel
que soit le moment de l'acceptation. Elle est « en direct » au rythme des passages : chaque synchronisation
Strava / Withings / banque / couverture, le passage quotidien et chaque acceptation relancent le job.

### 7.5 Clôture

- `Accepted` atteint (`current_value ≥ target_value`) → `Completed` immédiatement, y compris pendant le délai
  de grâce (une synchronisation tardive du dimanche soir compte).
- `Accepted` non atteint, `now ≥ ends_at + 48 h` → `Failed` ; à ce passage la cible est mesurée une dernière fois
  et, si elle est atteinte, `Completed` l'emporte.
- `Proposed`, `now ≥ ends_at` → `Expired` (on ne peut plus répondre après la fin de semaine).
- Les états terminaux sont définitifs : une donnée supprimée après coup ne retire ni la réussite ni l'XP.

Le verdict est pris au premier passage qui suit le délai de grâce, avec les données connues à cet instant : une
activité de la semaine synchronisée avant ce passage compte encore (`Completed`, même au-delà de
`ends_at + 48 h`) ; une fois `Failed` posé, il est définitif, même si cette activité arrive ensuite. Pendant la
semaine et la grâce, il ne dépend que des données de la semaine (bornes figées sur la ligne), de la cible figée et
de l'instant du passage, jamais de l'ordre des passages.

### 7.6 XP

`ReconvergeChallengeXp` upserte une entrée par défi `Completed` : `rule_key = challenge_completed`
(`XpRuleKey::ChallengeCompleted`, `isBonus() = true`), `source_type = challenge` (`XpSourceType::Challenge`),
`source_id = id du défi`, `points = xp_reward`, `domain` du défi, `occurred_at = resolved_at` ; il supprime les
entrées `challenge_completed` orphelines. Clé bonus : `AwardXp` ne la purge jamais dans sa fenêtre et
`UpdateStreaks` ne la compte jamais comme jour actif. Après la purge totale de `gamification:recalculate`, le
passage reconstruit l'XP depuis la table `challenges`. Aucune pénalité en cas d'échec.

### 7.7 Notifications

- `ChallengesProposedNotification` : une par ensemble créé (« 3 nouveaux défis cette semaine »).
- `ChallengeCompletedNotification` : une par défi réussi (« Défi réussi : Distance sportive »).
- Aucune notification d'échec ni d'expiration.

Canal `database` seul, notifications synchrones envoyées **dans** la transaction de `RunUserGamification`.
Danger nommé (docblock de `notifyChallenges`) : un canal mail ou broadcast, ou une notification `ShouldQueue`,
annoncerait un défi qu'un rollback supprime. Textes figés dans la locale de l'application à l'envoi, comme les
badges.

## 8. UI

Hub Joueur (`PlayerProfilePage`), section insérée après les tuiles d'XP, rendue par le composant Livewire
imbriqué `gamification-challenge-board` :

- titre « Défis de la semaine », sous-titre « Semaine du 28 sept. au 4 oct. » (format de date dans le fichier de
  langue) ;
- une carte par défi (composant Blade `gamification::challenge-card`) : icône et libellé du domaine, nom du
  template, description (« Parcourez 28 km cette semaine. »), semaine type (« Votre semaine type : 25 km »),
  récompense (« +50 XP »), puce d'état textuelle, barre de progression (`role="progressbar"`,
  `aria-valuenow`, `aria-valuetext` « 12 / 28 km »), « Progression mise à jour il y a 2 heures » ;
- sur un défi `Proposed` dont la semaine court : boutons « Relever le défi » et « Passer » (libellés accessibles
  « Relever le défi Distance sportive », `wire:confirm` traduit sur « Passer », `wire:loading.attr="disabled"`
  contre le double clic) ; si la mesure atteint déjà la cible, mention « Objectif déjà atteint : relevez le défi
  pour le valider » ;
- sous-section « Semaine dernière » avec les verdicts (ou « Clôture en attente des dernières synchronisations »
  pendant le délai de grâce) ;
- état vide neutre : « Aucun défi cette semaine. Les défis sont proposés chaque lundi à partir de vos 4 dernières
  semaines d'activité. » ;
- région `role="status"` `aria-live="polite"` annonçant « Défi accepté : Distance sportive » ; l'état n'est jamais
  porté par la seule couleur ; classes Tailwind littérales renvoyées par `ChallengeStatus::chipClass()` et
  `GamificationDomain`.

Tuile dashboard (`GamificationDashboardContribution`) : une ligne seulement s'il y a des défis cette semaine —
« 2 défis à relever cette semaine » tant qu'il reste des propositions, sinon « Défis : 1 / 2 réussis »
(réussis / engagés, refus exclus). Les deux clés vivent dans `lang/{fr,en}/dashboard.php`.

## 9. API

Ressource Lomkit `challenges` (`/api/challenges/search`), lecture seule : `ChallengesController` avec
`RejectsApiCreation`, `ChallengePolicy` à `false` pour `create` / `update` / `delete`, `ChallengeControl` scopant
sur `user_id`. Champs : `id`, `week_key`, `template_key`, `domain`, `metric`, `starts_at`, `ends_at`,
`baseline_value`, `target_value`, `current_value`, `xp_reward`, `status`, `accepted_at`, `resolved_at`,
`created_at`, `updated_at` ; `user_id` non exposé, aucune relation. Tri par défaut `starts_at desc`, limites
[10, 25, 50, 100].

## 10. Cas limites

- **Utilisateur sans historique ou avec une seule semaine active** : aucun défi, aucune notification, état vide
  neutre sur le hub, rien sur la tuile.
- **Backfill en milieu de semaine** : ensemble créé au passage suivant ; une cible peut déjà être atteinte, le
  défi reste `Proposed` jusqu'à l'acceptation, puis se valide au job déclenché par l'acceptation.
- **Synchronisation tardive** : comptée si l'activité est dans la semaine et arrive avant le premier passage qui
  suit `ends_at + 48 h` (au plus tard le passage quotidien de 02:00) ; une fois `Failed` posé par ce passage, le
  verdict est définitif.
- **Données supprimées** : en cours de semaine, la progression baisse ; après `Completed`, rien ne change.
- **Sorties moto saisies à la main** : le module moto n'émet pas d'événement de fin de saisie, la progression moto
  se met donc à jour au passage suivant (quotidien ou toute autre synchronisation).
- **Passage à l'heure d'hiver / d'été** : semaines de 167 h ou 169 h, bornes calculées par Carbon dans le fuseau
  de jeu.
- **Semaine 53** : clé `2026-W53`, rotation `53 mod n`, aucune ambiguïté d'année grâce à l'année ISO dans la clé.
- **Fuseau de jeu invalide** : repli sur le fuseau applicatif (comportement G2 de `GamificationCalendar`).
- **Changement de config** : cible et récompense figées sur les lignes ; la nouvelle config s'applique à la
  génération suivante. Un template retiré doit rester dans l'enum tant que des lignes le référencent (le cast
  échouerait).
- **Changement de fuseau de jeu** : la clé de semaine d'un même instant peut changer et un second ensemble
  apparaître ; à éviter en production.
- **Concurrence** : le job et la commande partagent déjà le verrou `ProcessUserGamificationJob::overlapKey` ;
  l'action Livewire ne le prend pas, la transition conditionnelle suffit (la clôture ne touche que des défis dont
  la semaine est finie, l'utilisateur ne peut répondre que pendant la semaine, la mise à jour de progression ne
  touche pas `status`).
- **Suppression de l'utilisateur** : `DeleteUserGamificationData` supprime ses défis et ses notifications
  `ChallengesProposedNotification` / `ChallengeCompletedNotification` ; le job trouvant un utilisateur absent
  sort sans rien faire.

## 11. Anti-abus et implications G5

Les sources sont en partie déclaratives (sorties moto créées à la main, activités Strava manuelles). Le design
borne ce qu'un joueur peut extraire :

- **Cible issue de son propre historique** : la médiane absorbe une semaine aberrante (vraie ou fausse) dans un
  sens comme dans l'autre ; le plancher empêche une cible triviale obtenue en levant le pied plusieurs semaines
  (« sandbagging ») ; le plafond borne une cible absurde issue d'une donnée erronée.
- **Éligibilité** : 2 semaines actives sur 4, pas de défi sur un domaine découvert la veille.
- **Récompense fixe et plafonnée** : un défi par domaine et par semaine, 50 XP chacun, donc 150 XP au plus par
  semaine, indépendamment du volume ; l'échec est gratuit, donc accepter tôt domine et attendre d'avoir atteint
  la cible ne rapporte rien de plus.
- **Ensemble figé** : passer un défi n'en propose pas un plus facile ; régénérer ne relance pas la rotation.
- **Réussite définitive** : créer une fausse sortie, valider le défi puis la supprimer garde 50 XP (l'XP de base
  de la sortie disparaît avec elle). Risque accepté, borné par le plafond hebdomadaire.

Pour G5 : l'XP de défi est la composante la plus équitable entre joueurs (cibles relatives à chacun, plafond
commun), mais le leaderboard devra décider s'il agrège les clés bonus et traiter la confiance des sources
déclaratives, déjà exploitables via l'XP de base. Les défis entre utilisateurs exigeront des cibles absolues
partagées : ils ajouteront une origine aux défis plutôt que de réutiliser la médiane individuelle.

## 12. Tests

TDD par tâche, PHPUnit en classes, `#[Test]`, noms `it_...`, factories avec états.

- Unit (`tests/Unit/Gamification`) : `ChallengeStateTest` (transitions légales, refus typés, `evolve`, fabrique),
  `ChallengeTargetCalculatorTest` (tableau des exemples du §4.3).
- Feature (`tests/Feature/Gamification`) : `GamificationWeekTest` (bornes W40, frontière dimanche 23:59:59 /
  lundi 00:00, W43 heure d'hiver, W53, fuseau invalide), `WeeklyMetricMeterTest` (bornes demi-ouvertes, Carbon
  zoné), `ChallengeTemplateKeyTest`, `ChallengeSettingsTest`, `ChallengeUnitTest`, `ChallengeStatusTest`,
  `ChallengeModelTest` (ULID minuscules, unicité, purge), `TransitionChallengeTest` (horodatages,
  compare-and-set), `ProposeWeeklyChallengesTest` (cibles, rotation, ensemble figé, idempotence, frontières),
  `ResolveChallengesTest` (complétion en direct, grâce, expiration, définitif), `ChallengeNotificationsTest`,
  `RespondToChallengeTest`, `ChallengeBoardTest` (actions, 404 / 409, a11y, fr + en), `ChallengeCardTest`,
  ajouts dans `RunUserGamificationTest` (rollback, une notification par ensemble), `ProcessUserGamificationJobTest`
  (XP conservée par passage fenêtré et recalcul complet), `AwardXpTest`, `UpdateStreaksTest`,
  `GamificationApiScopeTest`, `GamificationDashboardContributionTest`, `PlayerProfilePageTest`,
  `GamificationSeederTest`, `GamificationTranslationsTest` (parité fr / en automatique).
- Goals : ajouts dans `tests/Unit/Goals/GoalProgressCalculatorTest.php` ; les tests Objectifs existants restent
  verts sans modification.

## 13. Décisions prises

0. **Alignement G2 / G3** : tout ce qui écrit de l'XP passe par le ledger avec une clé bonus reconvergée dans la
   transaction unique de `RunUserGamification`, profil rafraîchi une fois en fin de passage, notifications
   database dans la transaction, découpage temporel par `GamificationCalendar`, identifiant de source non-modèle
   (`challenge`), purge par le listener existant.
1. **Semaine ISO, lundi 00:00 Europe/Paris, intervalle demi-ouvert, clé `week_key` = `2026-W40`.** Rejeté :
   fenêtre glissante de 7 jours (pas de semaine commune, ensembles instables) ; semaine du dimanche (non ISO) ;
   colonne `date` `week_start` (le cast `date` écrit `Y-m-d H:i:s`, que SQLite garde tel quel et que MySQL
   tronque : l'égalité sur la date diverge entre les tests et la production).
2. **Templates en enum + config, sans classes taguées ni table.** Tous les templates partagent le même algorithme
   et seule la métrique change. Rejeté : un contrat `ChallengeTemplate` tagué (sept classes identiques) ; une
   table catalogue (aucune édition admin, aucun besoin d'API, et une synchronisation partagée de plus).
3. **Mesure via une méthode publique `GoalProgressCalculator::measure()`** dont `currentValue(Goal)` devient
   délégataire, et dépendance déclarée de `functional/gamification` vers `functional/goals`. Rejeté : un `Goal`
   transitoire non persisté (couplage aux internes du modèle) ; réimplémenter les agrégations (divergence avec
   l'écran Objectifs) ; agréger par semaine en SQL (`YEARWEEK` / `strftime` non portables, fuseau impossible).
4. **Métriques retenues : celles de `GoalMetric` bornées par une période, hors finance.** Rejeté : investissement
   hebdomadaire (pousser à investir plus chaque semaine est un mauvais conseil financier) et valeur de
   portefeuille (subie, dictée par le marché) ; `Manual` (aucune donnée) ; `TodoCompletionRate` (ratio sur toutes
   les tâches, non borné) ; nouvelles métriques Santé / Tâches (modifieraient l'écran Objectifs ; les tâches,
   entièrement créées par l'utilisateur, se farment en un clic ; « se peser plus » n'a pas de sens).
5. **Cible = médiane des 4 dernières semaines (zéros compris) × 1,10, arrondie au pas supérieur, bornée par un
   plancher et un plafond par template ; éligibilité à 2 semaines actives.** Rejeté : la semaine précédente seule
   (volatile, manipulable en une semaine) ; la moyenne (tirée par une semaine aberrante) ; le record (décourageant) ;
   une cible au prorata du jour d'acceptation.
6. **Un défi par domaine éligible, template en rotation par numéro ISO.** Rejeté : tirage aléatoire (non
   déterministe, intestable) ; tous les templates éligibles (jusqu'à sept défis, bruit) ; choix par l'utilisateur
   (relève de G6).
7. **Génération paresseuse dans l'orchestrateur, ensemble figé dès qu'il est non vide.** Rejeté : planification
   dédiée le lundi 00:05 (doublon du passage quotidien, et un échec du lundi laisserait la semaine vide) ;
   régénération quand l'historique change (défis qui bougent sous les yeux, rotation manipulable).
8. **Six statuts en State pattern, dont `Expired` ajouté à la roadmap.** L'absence de réponse et le refus
   explicite sont deux concepts distincts. Rejeté : laisser `Proposed` indéfiniment (une proposition passée
   paraîtrait encore acceptable) ; assimiler le silence à `Declined` ; un enum de statut avec des `match` répartis
   dans les actions (règles de transition dispersées).
9. **Progression sur toute la semaine, réussite constatée en direct, échec après 48 h de grâce, états terminaux
   définitifs.** Rejeté : progression comptée depuis `accepted_at` (pénalise la découverte tardive et contredit une
   base calculée sur des semaines entières) ; verdict unique en fin de semaine (récompense retardée jusqu'à neuf
   jours) ; réussite révocable (notifications à répétition, contraire aux badges définitifs) ; refus d'accepter un
   défi déjà atteint (aucun gain d'XP à empêcher, erreur incompréhensible) ; grâce de 24 h (les synchronisations
   Strava ne sont pas planifiées et peuvent attendre l'utilisateur).
10. **Récompense fixe de 50 XP, figée à la proposition, sans pénalité d'échec.** Rejeté : XP proportionnelle à la
    cible (avantage aux gros volumes et à la triche) ; pénalité (anxiogène et contraire au ledger additif) ; XP
    relue en config à chaque passage (changerait une promesse déjà affichée).
11. **Notifications : une par ensemble proposé, une par réussite, database seule, dans la transaction.** Rejeté :
    notification d'échec ou d'expiration (anxiogène) ; mail (bruit) ; une notification par défi proposé (trois
    cloches le lundi).
12. **Réponse par actions Livewire d'un composant dédié, API REST en lecture seule.** Rejeté : action Lomkit
    `accept` / `decline` (élargit la surface d'écriture de l'API sans client qui la consomme) ; actions sur
    `PlayerProfilePage` (composant déjà chargé, et chaque clic re-rendrait toute la page).
13. **Accès par chargement scopé + policy `respond` ; refus métier en 409 natif.** La policy ne répond qu'à « ce
    défi est-il le sien », l'état et la semaine relèvent du domaine (`permissions-for-access-only`).
    `ChallengeNotRespondableException` est HTTP-native car son seul destin est une réponse 409 ;
    `IllegalChallengeTransitionException` et `StaleChallengeStatusException` restent des exceptions de domaine
    rapportées, car levées dans le job elles signalent un bug. Rejeté : un `abort(409)` répété ; une exception HTTP
    pour le compare-and-set (masquerait un bug du job dans les rapports).
14. **Concurrence par compare-and-set, sans verrou dans l'action Livewire.** Rejeté : prendre le verrou partagé du
    job dans l'action (bloquerait l'utilisateur pendant un recalcul).
15. **Acceptation suivie d'un job.** Rejeté : évaluer la réussite de façon synchrone dans l'action (dupliquerait
    l'orchestrateur hors de sa transaction et de son verrou).
16. **Unité `ChallengeUnit` (km, m, h, nombre).** Rejeté : élargir `BadgeUnit` (concept distinct, ni mètres ni
    heures) ; réutiliser `GoalMetric::defaultUnit()` (libellés français en dur).
17. **Textes dans `lang/{fr,en}/challenges.php`, lignes de tuile dans `dashboard.php`, pluriels par
    `trans_choice`.** Rejeté : textes en base (ne suivraient pas la locale) ; français en dur.

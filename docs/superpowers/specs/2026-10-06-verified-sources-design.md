# Sources vérifiées — pré-requis G5 — design

> Date : 2026-10-06. Pré-requis de la phase G5 de la roadmap `2026-07-03-gamification-roadmap-design.md` (section 5) ;
> livre les quatre points du §16 de `2026-10-05-anti-cheat-hardening-design.md` et les constats de la revue de sécurité
> finale de ce chantier.
> Base : `feature/anti-cheat-hardening` (non mergée). Branche : `feature/verified-sources`.
> Dépôts SDK : `Foutraz/SDK_Withings` (`foutraz/withings` 1.0.0 → 1.1.0), `Foutraz/SDK_Strava` (`foutraz/strava` 1.0.0 → 1.1.0).

## 1. Objectif

Le §7 du durcissement a décidé que le classement G5 ne compterait que l'XP de base adossée à une source synchronisée
(`sport_activity`, `health_measurement_day`, `exploration_daily_cells`). La revue finale a montré que « synchronisé »
n'est pas « vérifié » : une pesée saisie à la main dans l'application Withings, une activité Strava manuelle ou un trajet
moto enregistré comme sortie vélo arrivent aujourd'hui comme des mesures d'appareil ; un même compte Strava peut nourrir
plusieurs joueurs ; une activité ancienne importée après coup déplace une période passée.

Ce chantier fait de « synchronisé » un « vérifié » : chaque ligne synchronisée porte sa provenance et un verdict calculé
par le serveur à partir des seules données du fournisseur, le jeu ne se nourrit que des lignes vérifiées, un compte
fournisseur n'alimente qu'un compte du tableau de bord, et le classement d'une période close est figé une fois pour
toutes. G5 construira l'interface du classement sur ces garanties ; aucune surface publique n'est ouverte ici.

Le suivi reste prioritaire (roadmap, hypothèse 3) : aucune donnée n'est refusée ni masquée dans les écrans de suivi et
les Objectifs. Seules les conséquences ludiques changent.

## 2. Périmètre

Inclus :

- `foutraz/withings` 1.1.0 : provenance des groupes de mesures (`attrib`, `category`, `deviceid`, `created`) sur le DTO
  `Measurement`, enums `MeasureAttribution` et `MeasureCategory`, `getmeas` limité aux mesures réelles (`category=1`)
  par défaut et suivi de la pagination `more` / `offset`, suite PHPUnit avec `MockHandler` (le dépôt n'en a aucune),
  `CHANGELOG.md`, tag `1.1.0`.
- `foutraz/strava` 1.1.0 : `isManual`, `isFlagged`, `isTrainer`, `uploadId`, `externalId`, `deviceName` sur le DTO
  `Activity`, tests, `CHANGELOG.md`, README, tag `1.1.0`.
- Montée de version des deux SDK dans le tableau de bord (Quentin lance `composer`, authentification GitHub).
- Provenance stockée : `sport_activities.is_manual`, `strava_upload_id`, `strava_external_id`, `strava_device_name`,
  `body_measurements.attrib`, et sur les deux tables un verdict `verification` (enum partagée `SourceVerification`).
- Plausibilité des activités Strava : vitesse moyenne plafonnée par type de sport, activités signalées par Strava.
- Exclusion des lignes non vérifiées de toutes les conséquences ludiques (XP de base, séries, badges, défis, classement),
  y compris la couverture d'exploration (et les tracés virtuels).
- Un compte fournisseur actif pour un seul compte du tableau de bord : contrainte en base, refus au callback OAuth,
  migration qui refuse de s'appliquer sur des doublons, mesures Withings supprimées à la déconnexion.
- Périodes de classement (semaine de jeu, mois) figées à la clôture : tables `leaderboard_snapshots` et
  `leaderboard_snapshot_entries`, action de gel, commande planifiée `gamification:freeze-leaderboards`.

Exclus : l'interface du classement, le profil public, l'opt-in et les défis entre joueurs (G5) ; une indication « hors
jeu » sur les écrans de suivi (G6) ; la synchronisation des suppressions Strava ; une synchronisation Strava planifiée ;
la lecture du détail d'une activité (`GET /activities/{id}`) pendant la synchronisation ; tout plafond de vitesse
maximale (pics GPS) ; la vérification d'un fichier GPX / FIT téléversé (§12) ; toute nouvelle dépendance Composer / npm
hors des deux montées de version.

## 3. Écarts traités

| # | Écart | Preuve | Correctif |
|---|---|---|---|
| 1 | 365 pesées saisies à la main et antidatées valent 1 825 XP santé, 525 XP de paliers de série et les six badges santé (1 400 XP) : 3 750 XP | `Measurement::fromArray()` ignore `attrib` ; `HealthMeasurementDayXpRule` compte toute ligne | `attrib` exposé et stocké, verdict `self_reported` hors jeu (§4.1, §5.3, §6) |
| 2 | Les objectifs Withings (poids cible) arrivent comme des pesées | `getmeas` appelé sans `category` | `category=1` par défaut (§4.1) |
| 3 | Seule la première page de `getmeas` est lue | `more` / `offset` ignorés | pagination suivie (§4.1) |
| 4 | Une activité Strava manuelle compte comme une activité enregistrée | `Activity` n'expose pas `manual` | `isManual` exposé, verdict `self_reported` (§4.2, §5.2) |
| 5 | Un trajet moto enregistré comme sortie vélo (60 km/h de moyenne) vaut XP, badges, cellules | aucun contrôle de vitesse | plafond de vitesse moyenne par type, verdict `implausible` (§5.2) |
| 6 | Une activité signalée par la communauté Strava compte | `flagged` non exposé | `isFlagged`, verdict `implausible` (§5.2) |
| 7 | Une sortie virtuelle (Zwift) crée des cellules sur des îles du Pacifique | `RebuildUserCoverage` lit tout tracé | couverture limitée aux types à tracé réel (§6.2) |
| 8 | Un même athlète Strava ou utilisateur Withings relié à plusieurs comptes du tableau de bord | unicité `(user_id, provider)` seulement | unicité active `(provider, external_id)` (§7) |
| 9 | Se reconnecter après une déconnexion lève une violation d'unicité (500) | `unique(user_id, provider)` compte les connexions supprimées, `updateOrCreate` les ignore | unicité limitée aux connexions actives (§7.1) |
| 10 | Les mesures Withings d'une connexion déconnectée restent comptées : déconnecter puis relier le compte Withings à un autre joueur nourrit les deux | aucun écouteur de suppression dans `HealthServiceProvider` (le sport en a un) | `DeleteConnectionBodyMeasurements` (§7.3) |
| 11 | Une activité ancienne importée après coup déplace une période de classement passée | le classement serait calculé à la lecture | instantané figé à la clôture (§8) |

## 4. SDK

### 4.1 `foutraz/withings` 1.1.0

Le dépôt `Foutraz/SDK_Withings` n'a ni clone local, ni test, ni `phpunit.xml`, ni `CHANGELOG.md`. Il est cloné dans
`/home/qmari/Projects/perso/SDK_Withings`, à côté des autres SDK, et reçoit le même harnais que `foutraz/strava`
(`tests/TestCase.php` avec `MockHandler` et historique Guzzle, suites `Unit` et `Feature`).

`Foutraz\Withings\Dto\Measurement` (un objet par mesure, les champs de groupe recopiés sur chaque mesure) gagne, **à la
fin du constructeur et avec une valeur par défaut nulle** (les appels positionnels existants restent valides) :

| Propriété | Source | Type |
|---|---|---|
| `attrib` | `measuregrp.attrib` | `?int` |
| `category` | `measuregrp.category` | `?int` |
| `deviceId` | `measuregrp.deviceid` | `?string` |
| `createdAt` | `measuregrp.created` (horodatage serveur Withings) | `?DateTimeImmutable` |

et la méthode `attribution(): ?MeasureAttribution` (`tryFrom`, `null` pour une valeur absente ou non documentée).

`Foutraz\Withings\Enums\MeasureAttribution` (entier) : `Device` 0 (appareil, utilisateur connu), `DeviceAmbiguous` 1
(appareil, plusieurs utilisateurs possibles), `ManualEntry` 2 (saisie manuelle), `ManualAtAccountCreation` 4 (saisie à
la création du compte), `AutomaticBloodPressure` 5 (tensiomètre, meilleure valeur calculée), `ConfirmedByUser` 7
(mesure détectée puis confirmée), `SameAsDevice` 8 (équivalent à 0) ; `isDeviceCaptured(): bool` vrai pour 0, 1, 5, 7 et
8, faux pour 2 et 4. `Foutraz\Withings\Enums\MeasureCategory` : `Real` 1, `UserObjective` 2.

`ManagesMeasurements::getmeas(int $userid, ?int $lastUpdate = null, MeasureCategory $category = MeasureCategory::Real): array`
envoie `category` à chaque requête et suit la pagination : tant que la réponse porte `more` vrai, la requête suivante
envoie `offset` ; un `offset` absent ou qui n'avance pas lève `ActionFailed` (« Withings getmeas pagination did not
advance. ») plutôt que de tronquer en silence ou de boucler. Les mesures de toutes les pages sont rendues dans l'ordre.

`CHANGELOG.md` (format Keep a Changelog) : 1.1.0 — Added (champs, enums, suite de tests), Changed (`getmeas` ne demande
plus que les mesures réelles par défaut ; `MeasureCategory::UserObjective` pour les objectifs), Fixed (pagination) ;
1.0.0 — version initiale.

### 4.2 `foutraz/strava` 1.1.0

`Foutraz\Strava\Dto\Activity` gagne, en fin de constructeur, avec une valeur par défaut nulle :

| Propriété | Champ Strava (`SummaryActivity`) | Type |
|---|---|---|
| `isManual` | `manual` — « Whether this activity was created manually » | `?bool` |
| `isFlagged` | `flagged` — « Whether this activity is flagged » | `?bool` |
| `isTrainer` | `trainer` — « Whether this activity was recorded on a training machine » | `?bool` |
| `uploadId` | `upload_id` (int64), à défaut `upload_id_str` | `?int` |
| `externalId` | `external_id` — « The identifier provided at upload time » | `?string` |
| `deviceName` | `device_name` — « The name of the device used to record the activity » | `?string` |

Un champ absent reste `null` (jamais `false`) : le tableau de bord distingue « non manuelle » de « inconnue ».
README (section Activities) et `CHANGELOG.md` mis à jour ; tag `1.1.0`.

### 4.3 Publication et consommation

Pour chaque SDK : worktree hors du dépôt, branche `feature/…`, commits gitmoji, PR vers `main`, merge, tag `1.1.0` sur
le commit de merge, release GitHub reprenant la section du changelog. Le tableau de bord exige ensuite
`foutraz/strava:^1.1` et `foutraz/withings:^1.1` ; **Quentin lance `composer require`** (dépôts VCS, authentification
GitHub) et le lock est commité. Les deux versions sont rétrocompatibles : la suite existante doit rester verte sans
modification avant toute autre tâche.

## 5. Provenance et verdict

### 5.1 Verdict partagé

`Technical\Integrations\Enums\SourceVerification` (chaîne, colonne `verification` `string(16)`, jamais d'enum SQL) :

| Cas | Valeur | Sens |
|---|---|---|
| `Verified` | `verified` | mesurée ou enregistrée par un appareil, plausible |
| `SelfReported` | `self_reported` | saisie à la main chez le fournisseur |
| `Implausible` | `implausible` | synchronisée mais invraisemblable (vitesse, signalement Strava) |
| `Unknown` | `unknown` | provenance absente : ligne antérieure à ce chantier, champ non fourni, attribution non documentée |

Le verdict est **calculé par l'action de synchronisation à partir des seules données du fournisseur** et réécrit à
chaque synchronisation. Il n'est ni dans `fields()` des ressources Lomkit (toute écriture d'API est refusée en 422,
comme `recorded_at`), ni recalculé quand le propriétaire modifie `name` ou `sport_type` par l'API : un type corrigé à la
main ne peut pas blanchir une activité invraisemblable, et la synchronisation suivante rétablit le type et le verdict
de Strava. Les faits bruts sont stockés à côté pour l'audit.

### 5.2 Strava

`UpsertStravaActivity` écrit `is_manual` (`isManual`), `strava_upload_id`, `strava_external_id`, `strava_device_name`
et `verification`, dans cet ordre de décision :

1. `isManual` nul → `Unknown` ;
2. `isManual` vrai → `SelfReported` ;
3. `isFlagged` vrai → `Implausible` ;
4. `distance > 0` et `movingTime ≤ 0` → `Implausible` ;
5. `distance × 3,6 / movingTime` (km/h) **strictement supérieur** au plafond du type → `Implausible` ;
6. sinon `Verified`.

Plafond porté par l'enum : `SportType::plausibleAverageSpeedCeilingKmh(): int` — `Run`, `Walk`, `Hike` : 25 ; `Swim` :
10 ; `Ride`, `VirtualRide`, `Workout`, `Other` : 50. Le type est celui de Strava (`SportType::fromStrava`), les types
réduits à `Other` (`TrailRun`, `EBikeRide`, `AlpineSki`…) prennent 50.

| Activité | Calcul | Verdict |
|---|---|---|
| `Ride` 40 000 m en 3 600 s, non manuelle | 40 km/h | `verified` |
| `Ride` 50 000 m en 3 600 s | 50 km/h (plafond inclus) | `verified` |
| `Ride` 60 000 m en 3 600 s (trajet moto) | 60 km/h | `implausible` |
| `Run` 12 500 m en 1 800 s | 25 km/h | `verified` |
| `Run` 13 000 m en 1 800 s | 26 km/h | `implausible` |
| `Swim` 2 500 m en 900 s / 3 000 m en 900 s | 10 / 12 km/h | `verified` / `implausible` |
| `EBikeRide` (→ `Other`) 30 000 m en 1 800 s | 60 km/h | `implausible` |
| `Workout` 0 m en 3 600 s, non manuelle | — | `verified` |
| `Ride` 5 000 m en 0 s | — | `implausible` |
| `Workout` manuelle | — | `self_reported` |
| charge utile sans clé `manual` | — | `unknown` |

Les téléversements (`upload_id` non nul) sont acceptés s'ils sont plausibles : les synchronisations Garmin, Wahoo ou
Zwift sont elles aussi des téléversements, et rien dans l'API ne distingue un fichier d'appareil d'un fichier fabriqué
(§12). `upload_id`, `external_id` et `device_name` sont conservés pour l'audit et pour une règle plus fine si G5 en a
besoin.

### 5.3 Withings

`UpsertBodyMeasurement` reçoit `?int $attrib` (dernier paramètre, nul par défaut) et écrit `attrib` et `verification` :
`attrib` nul → `Unknown` ; attribution non documentée (`MeasureAttribution::tryFrom` nul, par exemple 15) → `Unknown` ;
`isDeviceCaptured()` → `Verified` ; sinon (2, 4) → `SelfReported`. `SyncWithingsMeasurementsJob` transmet
`$measurement->attrib`. Une mesure ambiguë (1) d'une balance partagée est une vraie pesée : elle compte.

### 5.4 Lignes existantes

Les migrations ajoutent `verification` avec la valeur par défaut `unknown` et les faits à `null` : **aucune ligne
existante n'est réputée vérifiée** (échec fermé). La première synchronisation complète réécrit le verdict de chaque
ligne renvoyée par le fournisseur ; une ligne qu'il ne renvoie plus (objectif Withings, activité supprimée sur Strava)
reste `unknown`, donc hors jeu mais visible. La livraison enchaîne donc resynchronisation complète puis recalcul (§13).
Les factories créent des lignes `verified` par défaut (états `manual()`, `implausible()`, `ofUnknownProvenance()`,
`manualEntry()`), si bien que les tests existants ne changent pas.

## 6. Ce que le verdict exclut

### 6.1 Matrice

| Ligne | Écrans de suivi, Objectifs | XP de base | Séries | Badges | Défis personnels | Classement G5 |
|---|---|---|---|---|---|---|
| activité / mesure `verified` | oui | oui | oui | oui | oui | oui |
| `self_reported`, `implausible`, `unknown` | oui | non | non | non | non | non |
| sortie moto, transaction, tâche (déclaratives) | oui | oui (hub privé) | oui | oui | oui (règle d'enregistrement à temps) | non |

Les familles synchronisées (sport, santé, exploration) ne comptent donc qu'une seule chose, partout : leurs séries et
badges privés sont exactement ceux que G5 pourra montrer publiquement, sans second calcul.

### 6.2 Où le filtre s'applique

- XP : `SportActivityXpRule` et `HealthMeasurementDayXpRule` ajoutent `where('verification', SourceVerification::Verified)`.
- Séries : dérivées des entrées d'XP non bonus (`UpdateStreaks`), elles suivent sans changement.
- Badges : `SportDistanceBadgeRule`, `SportActivityCountBadgeRule`, `HealthMeasurementDaysBadgeRule` filtrent de même ;
  les badges de série lisent les séries ; `ExplorationCellsBadgeRule` lit des cellules déjà vérifiées.
- Exploration : `RebuildUserCoverage` ne lit que les activités `verified` dont le type a un tracé réel
  (`SportType::withRealWorldRoutes()`, tous les types sauf `VirtualRide`). La carte d'exploration étant cette même
  projection, elle ne montre plus les tracés invraisemblables ou virtuels.
- Défis : `GoalMetricAggregator::totalsPerPeriod()` reçoit `MeasuredRows $rows = MeasuredRows::All` ; `WeeklyMetricMeter`
  passe `MeasuredRows::VerifiedOnly`, qui filtre les quatre agrégats `SportActivity` (`MetricAggregate::$verificationColumn`
  = `verification`) ; moto et exploration n'ont pas de colonne et ne sont pas filtrés.
- Objectifs : `GoalMetricAggregator::total()` (donc `GoalProgressCalculator`) garde toutes les lignes ; « 28 km » sur un
  Objectif compte la séance saisie à la main, « 28 km » sur un défi ne la compte pas.

## 7. Un compte fournisseur, un compte du tableau de bord

### 7.1 Contrainte

`integration_connections` gagne une colonne générée **virtuelle** `active_marker` (`unsignedTinyInteger`, nullable) =
`case when deleted_at is null then 1 end`, et deux index uniques :

- `integration_connections_user_provider_active_unique` sur `(user_id, provider, active_marker)` remplace
  `unique(user_id, provider)` ;
- `integration_connections_provider_account_active_unique` sur `(provider, external_id, active_marker)`.

MySQL et SQLite considèrent les `NULL` comme distincts dans un index unique : une connexion supprimée (marqueur nul) ou
sans `external_id` (calendriers, GoCardless) n'entre dans aucun conflit. L'expression `CASE` est portable (ni `CONCAT`,
ni `||`, ni fonction de date) ; une colonne virtuelle peut être ajoutée par `ALTER TABLE` sous SQLite et indexée sous
InnoDB. La contrainte couvre tous les écrivains d'`external_id`, y compris `SyncStravaAthleteJob`. Effet de bord voulu :
se reconnecter après une déconnexion ne viole plus l'unicité (écart 9).

Danger nommé de la migration : sous MySQL, `unique(user_id, provider)` sert d'index à la clé étrangère `user_id` ; le
nouvel index qui commence par `user_id` doit être créé **avant** de supprimer l'ancien.

### 7.2 Refus au callback

`Technical\Integrations\Actions\EnsureProviderAccountIsAvailable::handle(string $userId, IntegrationProvider $provider, string $externalId)`
lève `Technical\Integrations\Exceptions\ProviderAccountAlreadyLinkedException` si une connexion **active** d'un autre
utilisateur porte le même `(provider, external_id)`. Appelée par `FindOrCreateConnection` (Strava, quand le jeton porte
un athlète) et `FindOrCreateWithingsConnection` avant `updateOrCreate`. Le même utilisateur qui réautorise son compte
passe (mise à jour des jetons, inchangé).

L'exception étend `ConflictHttpException` (409, non rapportée) et se rend elle-même : `render()` redirige vers la page
`integrations` avec le message flashé `integration_error` : « Ce compte Strava est déjà relié à un autre compte du
tableau de bord. » (libellé du fournisseur via `IntegrationProvider::label()`). Le message ne révèle pas quel compte le
détient. Aucune connexion n'est créée ni modifiée, aucun job n'est lancé, le jeton obtenu est abandonné. La page
Intégrations affiche ce message dans un bandeau d'erreur. Une course entre deux liaisons simultanées tombe sur l'index
unique (erreur 500 rapportée, aucune donnée incohérente).

### 7.3 Déconnexion Withings

`Functional\Health\Listeners\DeleteConnectionBodyMeasurements`, branché par
`IntegrationConnection::deleting(DeleteConnectionBodyMeasurements::class)` dans `HealthServiceProvider::boot()`,
supprime les mesures de la connexion (`whereBelongsTo($connection, 'connection')`, suppression définitive : la table n'a
pas de `SoftDeletes`), comme `DeleteConnectionSportActivities` le fait pour le sport. Se reconnecter réimporte
l'historique complet (`SyncWithingsUserJob` → synchronisation sans `lastupdate`).

### 7.4 Migration et doublons existants

`technical/integrations/database/migrations/2026_10_06_000001_enforce_one_dashboard_account_per_provider_account.php` :

1. si des connexions actives partagent `(provider, external_id)`, lève
   `DuplicateProviderAccountLinksException` (liste `strava:99887766 (2)`) **avant tout changement de schéma** ;
2. ajoute `active_marker` s'il est absent, crée chaque index s'il est absent, supprime l'ancien index s'il existe :
   ré-exécutable après une interruption ;
3. `down()` recrée `unique(user_id, provider)` d'abord (clé étrangère), puis supprime les deux index et la colonne ;
   danger nommé : il échoue si un utilisateur s'est reconnecté entre-temps (deux lignes, dont une supprimée).

Aucune résolution automatique : Quentin déconnecte le doublon depuis la page Intégrations (les écouteurs de suppression
s'exécutent) puis relance la migration. La requête de contrôle est exécutée sur la production avant la livraison (§13).

## 8. Périodes de classement figées

### 8.1 Fenêtres

`Functional\Gamification\Enums\LeaderboardPeriod` : `Week` (`week`), `Month` (`month`).
`GamificationCalendar::leaderboardWindowOf(LeaderboardPeriod $period, CarbonInterface $moment): LeaderboardWindow` rend
la fenêtre demi-ouverte qui contient l'instant, bornes converties dans le fuseau applicatif (UTC) :

| Fenêtre | Clé | `starts_at` | `ends_at` | `closes_at` (48 h) |
|---|---|---|---|---|
| semaine 2026-W39 | `2026-W39` | `2026-09-20 22:00:00` | `2026-09-27 22:00:00` | `2026-09-29 22:00:00` |
| semaine 2026-W40 | `2026-W40` | `2026-09-27 22:00:00` | `2026-10-04 22:00:00` | `2026-10-06 22:00:00` |
| semaine 2026-W41 | `2026-W41` | `2026-10-04 22:00:00` | `2026-10-11 22:00:00` | `2026-10-13 22:00:00` |
| mois de septembre 2026 | `2026-09` | `2026-08-31 22:00:00` | `2026-09-30 22:00:00` | `2026-10-02 22:00:00` |
| mois d'octobre 2026 | `2026-10` | `2026-09-30 22:00:00` | `2026-10-31 23:00:00` | `2026-11-02 23:00:00` |

`2026-09-30 21:59:59` UTC est en septembre, `2026-09-30 22:00:00` en octobre (minuit à Paris). Le délai de grâce
`gamification.leaderboard.closing_grace_hours` (48 par défaut, entier 0..168, validé par `LeaderboardSettings::fromConfig()`,
exception `InvalidLeaderboardConfigException`) est propre au classement : il ne suit pas celui des défis.

### 8.2 XP vérifiée d'une fenêtre

`XpRuleKey::countsTowardsLeaderboard(): bool` (vrai pour `SportActivity`, `HealthMeasurementDay`,
`ExplorationDailyCells`) et `XpRuleKey::leaderboardKeys(): list<string>` matérialisent la règle du §7 du durcissement.
`Functional\Gamification\Services\VerifiedXpTotals::perUser(LeaderboardWindow $window)` rend, en **une requête groupée**,
la somme des `points` de ces clés par utilisateur pour `starts_at ≤ occurred_at < ends_at` (aucune fonction de date
SQL, bornes déjà en UTC). Depuis le §6, ces trois clés ne contiennent que des lignes vérifiées.

Exemple (W40) : l'utilisateur A a `sport_activity` 40 (`2026-09-28 08:00:00`), `health_measurement_day` 5
(`2026-09-29 00:00:00`), `exploration_daily_cells` 20 (`2026-10-04 21:59:59`), `moto_ride` 35, `finance_month` 30,
`streak_milestone` 25, `badge_award` 50, `challenge_completed` 50 dans la semaine, `sport_activity` 25 à
`2026-10-04 22:00:00` (W41) et 30 à `2026-09-27 21:59:59` (W39) ; B a `sport_activity` 20 (`2026-10-01 10:00:00`) ; C n'a
qu'une `moto_ride` → W40 = { A : 65, B : 20 }, C absent ; septembre = { A : 75 } ; octobre = { A : 45, B : 20 }.

### 8.3 Instantanés

| Table | Colonnes |
|---|---|
| `leaderboard_snapshots` | `id` ULID, `period` `string(8)`, `period_key` `string(16)`, `starts_at`, `ends_at`, `closes_at`, `frozen_at`, horodatages ; unique `(period, period_key)` |
| `leaderboard_snapshot_entries` | `id` ULID, `leaderboard_snapshot_id` (clé étrangère ULID, sans cascade), `user_id` (clé étrangère ULID, sans cascade), `verified_xp` `unsignedInteger`, horodatages ; unique `(leaderboard_snapshot_id, user_id)`, index `user_id` |

Une ligne de `leaderboard_snapshots` marque une période figée, même sans entrée. Une entrée par utilisateur dont l'XP
vérifiée de la fenêtre est positive, **quel que soit son statut public** : G5 filtrera les profils publics et calculera
les rangs à la lecture. Ni rang, ni détail par domaine. Les lignes ne sont jamais mises à jour.

### 8.4 Gel

`FreezeLeaderboardPeriods::handle(CarbonInterface $moment): int` (nombre de fenêtres figées), pour chaque période :

- dernière fenêtre close = la fenêtre qui contient `$moment`, reculée tant que `closes_at > $moment` ;
- première fenêtre à figer = celle qui suit la dernière figée (`ends_at` maximal de la période) ; **sans aucun instantané
  de la période, seulement la dernière fenêtre close** (aucun historique rétroactif : ces données étaient mutables) ;
- chaque fenêtre, dans l'ordre, dans sa propre transaction : ligne d'instantané (`frozen_at = $moment`), puis entrées
  de `VerifiedXpTotals::perUser()` insérées par lots de 500 (ULID générés, l'insertion de masse ne passe pas par
  `HasUlids`) ; une fenêtre déjà figée est ignorée.

Commande `gamification:freeze-leaderboards` (« Froze N leaderboard periods. »), planifiée `hourly()->withoutOverlapping()`
dans `GamificationServiceProvider`. Un arrêt du planificateur est rattrapé au passage suivant, dans l'ordre.

Exemples (aucun instantané au départ, grâce 48 h) :

- passage à `2026-10-06 21:59:59` → W39 et septembre figés (W40 se clôt à `22:00:00`) ; à `2026-10-06 22:00:00` → W40
  figée avec A 65 et B 20 ;
- une activité du 01/10 synchronisée ensuite (nouvelle entrée `sport_activity` de 100 pour A) → W40 reste A 65 ;
  `gamification:recalculate` ne touche pas aux instantanés ;
- dernier instantané W38, passage à `2026-10-14 00:00:00` → W39, W40, W41 figées dans cet ordre ; sans instantané →
  seulement W41 et septembre ;
- grâce 72 h → W40 se clôt à `2026-10-07 22:00:00`, et l'instantané porte ce `closes_at`.

`DeleteUserGamificationData` supprime les entrées de l'utilisateur supprimé ; les instantanés et les entrées des autres
joueurs restent.

## 9. Modèle de données

| Table | Changement | Migration |
|---|---|---|
| `integration_connections` | `active_marker` virtuelle, deux uniques actifs, ancien unique supprimé | `technical/integrations/database/migrations/2026_10_06_000001_enforce_one_dashboard_account_per_provider_account.php` |
| `sport_activities` | `is_manual` booléen nullable, `strava_upload_id` `unsignedBigInteger` nullable, `strava_external_id` et `strava_device_name` `string` nullables, `verification` `string(16)` défaut `unknown`, après `strava_id` | `functional/sport/database/migrations/2026_10_06_000002_add_provenance_to_sport_activities_table.php` |
| `body_measurements` | `attrib` `unsignedTinyInteger` nullable, `verification` `string(16)` défaut `unknown`, après `type` | `functional/health/database/migrations/2026_10_06_000003_add_provenance_to_body_measurements_table.php` |
| `leaderboard_snapshots` | création | `functional/gamification/database/migrations/2026_10_06_000004_create_leaderboard_snapshots_table.php` |
| `leaderboard_snapshot_entries` | création | `functional/gamification/database/migrations/2026_10_06_000005_create_leaderboard_snapshot_entries_table.php` |

Toutes ré-exécutables (`hasColumn`, `hasIndex`, `hasTable`), sans backfill ligne à ligne (la valeur par défaut porte
`unknown`), sans cascade, sans enum SQL, sans fonction de date, identifiants ULID.

## 10. Flux

- **Liaison** : callback OAuth → état vérifié → échange du jeton → `EnsureProviderAccountIsAvailable` (refus : redirection
  vers Intégrations) → `updateOrCreate` (unicité active en base) → job de synchronisation.
- **Synchronisation Strava** : `iterate()` → `UpsertStravaActivity` (faits + verdict, restaure une ligne supprimée) →
  `StravaActivitiesSynced` → gamification (règles filtrées).
- **Synchronisation Withings** (quotidienne, complète) : `getmeas` (`category=1`, toutes les pages) →
  `UpsertBodyMeasurement` (`attrib` + verdict) → `WithingsMeasurementsSynced` → gamification.
- **Déconnexion** : suppression de la connexion → activités (soft delete) et mesures (suppression) de la connexion
  retirées → marqueur actif nul, le compte fournisseur redevient libre.
- **Couverture** : bouton « Recalculer » ou `exploration:rebuild-coverage` → activités vérifiées à tracé réel seulement.
- **Gel** : toutes les heures → fenêtres closes non figées → instantanés.

## 11. Cas limites

- **Jeton Strava sans athlète** : `external_id` nul, aucune vérification à la liaison ; `SyncStravaAthleteJob` le pose
  ensuite et l'index unique refuse un athlète déjà relié ailleurs (job en échec, rapporté).
- **Même utilisateur, nouveau compte Strava** : `updateOrCreate` remplace `external_id`, l'unicité porte sur le nouveau.
- **A déconnecte, B relie le même compte** : autorisé ; A perd les activités et mesures de cette connexion, garde ses
  badges (définitifs, §12).
- **Activité supprimée sur Strava** : la synchronisation ne supprime rien ; vérifiée avant, elle le reste. Antérieure à
  ce chantier et jamais renvoyée, elle reste `unknown`.
- **Objectif Withings déjà stocké** : jamais renvoyé avec `category=1`, il reste `unknown`, visible, hors jeu.
- **Attribution changée par Withings** (1 → 0 après attribution de la mesure) : réécrite au passage quotidien.
- **Propriétaire qui change `sport_type`** : verdict inchangé jusqu'à la synchronisation suivante, qui rétablit le type
  Strava.
- **Verdict qui change sur une ligne ancienne** : l'XP de la fenêtre glissante quotidienne suit ; au-delà, le passage
  complet du dimanche ou `gamification:recalculate`.
- **Période sans aucune XP vérifiée** : instantané sans entrée.
- **Fuseau** : une entrée `health_measurement_day` est datée `00:00:00` UTC de son jour ; W40 commence à
  `2026-09-27 22:00:00` : le jour UTC du 28/09 est en W40 (découpage du §7 du durcissement, inchangé).
- **Notifications pendant la livraison** : un passage de gamification entre la migration et la resynchronisation voit
  les lignes `unknown`, l'XP baisse puis remonte ; un niveau peut être notifié une seconde fois. La procédure du §13
  l'évite (resynchronisations avant tout passage).

## 12. Risques résiduels acceptés

- **Fichier fabriqué** : un GPX / FIT d'un parcours jamais fait, horodaté et plausible (vitesse sous le plafond), est
  vérifié. Antidaté sur des jours passés, il peut combler une série. Orientation pour G5 : une série publique ne compte
  un jour que si une activité de ce jour a été vue par le tableau de bord (`created_at`, posé par le serveur) avant la
  fin du jour plus la grâce.
- **Trajet motorisé lent** : une moto sous 50 km/h de moyenne en `Ride`, une voiture sous 25 km/h en `Run`.
- **Types réduits à `Other`** : `TrailRun` et `VirtualRun` prennent le plafond de 50 km/h ; un `VirtualRun` crée des
  cellules.
- **Appareil porté par un autre** : physique, hors de portée.
- **Saut de compte séquentiel** : relier un athlète à A, le déconnecter, le relier à B : B regagne les badges que A garde
  (badges définitifs). Ni XP ni série en double, jamais deux comptes actifs en même temps.
- **Attribution ambiguë (1)** : une pesée d'un autre membre du foyer sur une balance partagée peut compter.

## 13. Livraison

1. **Avant** : sur la production MySQL,
   `SELECT provider, external_id, COUNT(*) FROM integration_connections WHERE deleted_at IS NULL AND external_id IS NOT NULL GROUP BY provider, external_id HAVING COUNT(*) > 1;`
   doit être vide (sinon déconnecter le doublon depuis Intégrations). Étape bloquante de la pull request.
2. Arrêter le worker de queue et le planificateur, déployer (le lock porte `foutraz/strava` et `foutraz/withings` 1.1.0),
   `php artisan migrate --force`.
3. Mettre en queue une synchronisation complète de chaque connexion Strava puis Withings (une ligne `tinker` dans la
   description de la PR : `SyncStravaActivitiesJob` sans `after`, `SyncWithingsMeasurementsJob` sans `lastUpdate`), puis
   relancer le worker : les synchronisations passent avant les passages de gamification qu'elles déclenchent.
4. `php artisan exploration:rebuild-coverage`, puis `php artisan gamification:recalculate`, puis relancer le
   planificateur ; le premier `gamification:freeze-leaderboards` fige la dernière semaine et le dernier mois clos.

## 14. Tests

TDD par tâche, PHPUnit en classes, `#[Test]`, noms `it_...`, factories, `faker()`, `travelTo(Carbon::parse('…', 'UTC'))`.

- SDK Withings : `tests/Unit/Dto/MeasurementTest.php`, `tests/Unit/Enums/MeasureAttributionTest.php`,
  `tests/Feature/Actions/ManagesMeasurementsTest.php` (corps de formulaire, pagination, arrêt sur `offset` immobile).
- SDK Strava : ajouts dans `tests/Unit/Dto/ActivityTest.php` et `tests/Feature/Actions/ManagesActivitiesTest.php`.
- Intégrations : `tests/Feature/Integrations/ProviderAccountUniquenessTest.php`,
  `tests/Feature/Integrations/ProviderAccountMigrationTest.php`, ajouts dans `StravaCallbackTest`, `WithingsCallbackTest`,
  `IntegrationsPageTest` ; `tests/Feature/Health/ConnectionDeletionTest.php`.
- Sport : `tests/Feature/Sport/StravaActivityProvenanceTest.php`, `tests/Feature/Sport/ActivityProvenanceMigrationTest.php`,
  ajouts dans `SyncActivitiesJobTest` et `ActivitiesApiScopeTest`.
- Santé : `tests/Feature/Health/MeasurementProvenanceTest.php`, `tests/Feature/Health/MeasurementProvenanceMigrationTest.php`.
- Jeu : `tests/Feature/Gamification/VerifiedSourcesGameTest.php`, ajouts dans `RebuildCoverageTest`,
  `GoalMetricAggregatorTest`, `WeeklyMetricMeterTest`, `ResolveChallengesTest`.
- Classement : `tests/Feature/Gamification/XpRuleKeyTest.php`, `LeaderboardWindowTest.php`, `VerifiedXpTotalsTest.php`,
  `LeaderboardSettingsTest.php`, `FreezeLeaderboardPeriodsTest.php`, `LeaderboardSnapshotModelTest.php` (relations,
  purge à la suppression d'un utilisateur).
- **Aucun test existant n'est modifié** : les factories créent des lignes vérifiées, à tracé réel, et les montées de
  version des SDK sont rétrocompatibles.

## 15. Décisions prises

0. **Alignement** : les SDK exposent les faits et la sémantique du fournisseur (`MeasureAttribution::isDeviceCaptured()`),
   le tableau de bord décide de la politique (verdict dans les modules sources, filtres dans le jeu, gel dans la
   gamification, unicité dans les intégrations). Aucune écriture d'XP hors du ledger, aucune dépendance nouvelle.
1. **Une ligne non vérifiée perd toutes ses conséquences ludiques, le suivi est inchangé (à confirmer par Quentin).**
   Rejeté : exclure seulement du classement (deux calculs d'XP, `xp_entries.is_verified`, séries et badges publics à
   recalculer à part, un niveau public et un niveau privé qui divergent) ; refuser ces lignes à la synchronisation
   (suivi d'abord) ; les traiter comme la moto, comptées dans le hub privé (les trois familles synchronisées sont
   exactement celles que G5 expose ; un seul calcul garde public et privé identiques). Coût accepté : une séance de
   musculation saisie à la main dans Strava ne rapporte plus rien.
2. **Verdict stocké par ligne, calculé à la synchronisation depuis les données du fournisseur, jamais sur une
   modification du propriétaire.** Rejeté : la nature par métrique seule (décision 9 du durcissement : ces deux tables
   mêlent désormais les deux natures, le déclencheur qu'elle annonçait) ; un écouteur `saving` qui recalcule depuis les
   colonnes stockées (`sport_type` est modifiable par l'API, un type corrigé blanchirait le verdict, et les factories
   devraient produire des vitesses plausibles) ; filtrer sur les faits bruts dans chaque requête (vitesse par type en SQL
   recopiée dans six requêtes) ; un booléen `is_verified` (perd la raison, utile à l'audit et à G6).
3. **Faits stockés à côté du verdict : `is_manual`, `strava_upload_id`, `strava_external_id`, `strava_device_name`,
   `attrib`.** Rejeté : la charge utile complète dans `raw` (volume, colonne inutilisée aujourd'hui) ; la catégorie
   Withings (seule la catégorie 1 est demandée) ; `deviceid` et `created` Withings (l'attribution suffit, exposés par le
   SDK pour plus tard).
4. **Strava vérifiée = non manuelle, non signalée, vitesse moyenne ≤ 25 / 10 / 50 km/h selon le type ; téléversements
   acceptés (à confirmer).** Rejeté : exclure tout téléversement (Garmin, Wahoo, Zwift sont des téléversements) ;
   reconnaître des motifs d'`external_id` (`garmin_push_…`, non documentés, fragiles) ; la vitesse maximale (pics GPS,
   faux positifs) ; un appel de détail par activité pour `device_name` (limites de débit Strava, et le champ est déclaré
   dans `SummaryActivity`) ; des plafonds par type Strava brut (l'enum réduit déjà les types, laxisme accepté).
5. **Withings vérifiée = attribution 0, 1, 5, 7 ou 8 ; 2 et 4 saisies ; toute autre valeur ou absence inconnue.**
   Rejeté : une liste noire {2, 4} (une attribution nouvelle compterait par défaut) ; exclure 1 (vraie pesée d'une balance
   partagée) ; l'écart `created − date` (une balance hors ligne synchronise tard légitimement, la saisie est déjà
   repérée par l'attribution).
6. **`getmeas` : `category=1` par défaut et pagination, publiés en 1.1.0 ; champs des DTO ajoutés en fin de
   constructeur avec `null` par défaut.** Rejeté : garder le défaut et passer la catégorie depuis le tableau de bord (le
   SDK public continuerait de rendre des objectifs comme des mesures) ; une version majeure (aucun appelant ne casse, seul
   le résultat change pour les objectifs, ce qui est la correction) ; tronquer en silence sur un `offset` immobile.
7. **Lignes existantes `unknown` (échec fermé) jusqu'à la resynchronisation complète, ordonnée par la procédure de
   livraison (à confirmer).** Rejeté : un backfill `verified` (des pesées manuelles et des objectifs jamais renvoyés
   resteraient comptés pour toujours) ; un backfill de la vitesse seule (la nature manuelle reste inconnue) ; supprimer
   les lignes inconnues (suivi).
8. **Couverture d'exploration issue des seules activités vérifiées à tracé réel, carte comprise.** Rejeté : une colonne
   `verified_first_seen_at` sur les cellules (seconde projection pour un seul cas) ; garder les tracés virtuels (le monde
   de Zwift se projette sur des coordonnées réelles).
9. **Défis mesurés sur les lignes vérifiées (`MeasuredRows::VerifiedOnly`), Objectifs sur toutes.** Rejeté : la même
   portée pour les deux (soit les Objectifs perdent les séances manuelles, soit les défis comptent ce que l'XP ignore) ;
   un filtre implicite dans `totalsPerPeriod()` (comportement caché à l'appelant).
10. **Unicité active par colonne virtuelle `active_marker` et index composites.** Rejeté : un contrôle applicatif seul
    (course, et `SyncStravaAthleteJob` écrit aussi `external_id`) ; un index partiel (absent de MySQL) ; une colonne
    générée concaténée (`CONCAT` absent de SQLite avant 3.44, `||` est un OU logique sous MySQL) ; supprimer
    définitivement la connexion à la déconnexion (clés étrangères des lignes synchronisées) ; restaurer la connexion
    supprimée à la reconnexion (ne règle pas l'unicité entre utilisateurs).
11. **Refus au callback par `ProviderAccountAlreadyLinkedException` (409 qui se rend en redirection vers Intégrations avec
    un message flashé, sans nommer l'autre compte).** Rejeté : l'erreur 500 actuelle des callbacks ; une page 409 sans
    vue dédiée (aucun message) ; déplacer la liaison vers le nouveau compte (un athlète nourrirait plusieurs joueurs à
    tour de rôle, et les lignes synchronisées seraient orphelines) ; un callback `render()` dans `bootstrap/app.php`.
12. **Doublons existants : la migration refuse de s'appliquer et les liste.** Rejeté : garder la plus ancienne connexion
    automatiquement (choix silencieux, et une suppression dans une migration n'exécute pas les écouteurs : les lignes
    synchronisées du doublon continueraient de compter) ; laisser l'index échouer (erreur opaque).
13. **Déconnexion Withings : mesures supprimées comme les activités du sport.** Rejeté : filtrer le jeu sur les
    connexions actives (jointure dans chaque règle, et le suivi montrerait des mesures d'une connexion révoquée) ;
    ajouter `SoftDeletes` à `body_measurements` (changement de modèle sans lecteur des lignes supprimées ; la
    reconnexion réimporte tout).
14. **Classement figé par instantanés écrits une fois à la clôture (fin + 48 h), semaine et mois, pour tous les
    utilisateurs ayant de l'XP vérifiée, total seul, sans rétroactivité (à confirmer).** Rejeté : une règle
    « enregistré avant la clôture » sur les lignes synchronisées (elle n'empêche que les ajouts ; la reconvergence du
    ledger change les points et supprime des entrées d'une période close) ; un `recorded_at` sur `xp_entries` (le
    recalcul recrée les lignes) ; une seule table sans ligne de période (période vide indiscernable d'une période non
    figée) ; stocker le rang (dépend de l'ensemble des profils publics, G5) ; ne figer que les profils publics
    (`is_public` n'existe pas encore) ; figer tout l'historique au premier passage (données mutables jusque-là, sans
    intérêt) ; un détail par domaine (aucun classement par domaine prévu en G5).
15. **Pas de `recorded_at` sur les lignes synchronisées.** `created_at` (posé par le serveur, non inscriptible depuis la
    faille 7 du durcissement) sert d'instant « vu pour la première fois » ; l'instantané fige le classement ; la règle G4
    des métriques synchronisées (backfill du mercredi valable) est inchangée.
16. **Gel par une commande horaire avec rattrapage.** Rejeté : geler dans `RunUserGamification` (par utilisateur, à des
    instants différents) ; une tâche hebdomadaire calée sur la clôture (un passage manqué perdrait la période).
17. **Délai de grâce propre au classement (`gamification.leaderboard.closing_grace_hours`).** Rejeté : réutiliser celui
    des défis (deux concepts : modifier l'un ne doit pas déplacer l'autre).

## 16. Entrées pour G5

- Classement d'une période close : `leaderboard_snapshot_entries` de son instantané ; période ouverte :
  `VerifiedXpTotals::perUser()` de la fenêtre courante ; filtre des profils publics et rangs à la lecture.
- Séries et badges publics des familles `sport_*`, `health_*`, `exploration_cells` : ceux du hub, déjà vérifiés ; une
  attribution à `measured_value` nul n'est montrée que si la mesure actuelle (vérifiée) atteint encore le seuil (§7 du
  durcissement).
- Défis entre joueurs : `WeeklyMetricMeter` (lignes vérifiées) sur des métriques `! isSelfReported()`.
- Le §16 du durcissement est levé une fois cette branche livrée et la procédure du §13 exécutée.

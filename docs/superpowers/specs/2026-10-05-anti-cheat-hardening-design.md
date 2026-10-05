# Durcissement anti-triche avant G5 — design

> Date : 2026-10-05. Prérequis de la phase G5 de la roadmap `2026-07-03-gamification-roadmap-design.md` (section 5) ;
> reprend la liste « Durcissement à planifier avant G5 » (§11) et le changement de config du §10 de
> `2026-10-01-gamification-g4-challenges-design.md`.
> Base : `develop` (G1 à G4). Branche : `feature/anti-cheat-hardening`.

## 1. Objectif

Avant d'ouvrir des surfaces publiques (G5 : profil public opt-in, classement, défis entre joueurs), faire en sorte que
les mécaniques de jeu ne se nourrissent que de données **plausibles**, **datées honnêtement** et **traçables** :
fermer les failles prouvées par les revues de sécurité (antidatage pour devenir éligible à un défi, ligne fabriquée
qui décroche un badge), figer ce qui doit l'être (clôture des défis, mesure à l'attribution d'un badge) et trancher dès
maintenant quelle XP le classement G5 comptera.

Le suivi reste prioritaire (roadmap, hypothèse 3) : aucune saisie légitime n'est refusée — une sortie oubliée saisie
après coup, un historique Strava importé le mercredi restent acceptés et visibles partout. Seules leurs conséquences
ludiques changent.

## 2. Périmètre

Inclus :

- Validation d'entrée dans les modules sources, **ressources REST Lomkit et formulaires Livewire** qui créent les mêmes
  lignes : dates jamais futures, bornes plausibles, champs synchronisés par Strava non modifiables, identifiant et
  horodatages réservés au serveur.
- Protection commune de **toutes** les ressources REST (§4.1) : identifiant et horodatages réservés au serveur par une
  règle par défaut de la classe de base (`rules()` renvoie `serverManagedFieldRules()`), `trip-routes` sans aucune écriture
  possible, plafond de 100 opérations par requête `mutate` et limite de débit nommée sur toutes les routes REST.
- Activités Strava synchronisées : ni suppression, ni restauration, ni suppression définitive par l'API ; la
  synchronisation restaure et met à jour une activité supprimée au lieu d'échouer.
- Cellules explorées : API en lecture seule, reconstruction de couverture convergente.
- Tâches : `completed_at` posé par le serveur.
- Règle d'enregistrement à temps (antidatage) pour l'historique et la progression des défis.
- Provenance : `badge_awards.measured_value`.
- Décision de la règle d'XP du classement G5 (implémentée en G5) et pré-requis de durcissement des sources synchronisées
  (§16).
- XP finance mensuelle : un mois ne compte que par un montant net acheté minimum et jamais avant l'existence du compte et
  de la position (§12).
- Suivis G4 : instant de clôture figé par défi (`challenges.closes_at`, migration + backfill) et historique de
  proposition agrégé en SQL (une requête groupée par template), `Goal::currentValue` inchangé.

Exclus : le classement, le profil public et les défis entre joueurs eux-mêmes (G5) ; une colonne de source par ligne
(décision 9) ; la détection des activités Strava manuelles et des pesées Withings saisies à la main (les SDK
`foutraz/strava` et `foutraz/withings` ne les exposent pas : pré-requis G5, §16) ; la validation métier des autres
ressources Lomkit (positions, objectifs, utilisateurs, événements), qui ne nourrissent pas le jeu : seules les protections
communes du §4.1 s'y appliquent ; le fuseau du formulaire moto (§11) ; un plafond d'XP par jour ; la révocation de badges ; toute nouvelle
dépendance Composer / npm.

## 3. Failles traitées

| # | Faille | Preuve | Correctif |
|---|---|---|---|
| 1 | Sorties moto antidatées pour devenir éligible à un défi, valider puis supprimer les preuves | `MotoRideResource::rules()` : `started_at` `date` sans borne | dates passées (§4.2), enregistrement à temps (§5) |
| 2 | Cellules explorées créées ou antidatées par l'API | `ExploredCellsController` accepte `create`, `first_seen_at` libre | API lecture seule, couverture convergente (§4.5) |
| 3 | Une sortie de 20 000 km décroche l'Or moto (700 XP de badges) | `distance` `min:0` sans plafond | 2 000 km par sortie (§4.2) |
| 4 | Activité Strava dont le propriétaire porte la distance à 5 000 km | `SportActivityResource::rules()` : `distance` modifiable malgré `strava_id` | champs Strava interdits (§4.4) |
| 5 | Vente à 0 €, quantités qui débordent la colonne | `InvestmentTransactionResource` : `quantity` / `unit_price` `min:0` sans max | `gt:0` et plafonds (§4.3) |
| 6 | `completed_at` antidaté : séries Tâches de 100 jours, badges | `TaskResource::rules()` : `completed_at` `date` libre | horodatage serveur (§4.6) |
| 7 | `created_at` et `id` forgeables par toute mutation | `PerformMutation::mutateModel()` fait `forceFill($attributes)` sur chaque champ de `fields()` | `missing` (§4.1) |
| 8 | Aucune trace de la mesure qui a valu un badge | `badge_awards` sans valeur mesurée | `measured_value` (§6) |
| 9 | Délai de grâce relu en config à la résolution | `ResolveChallenges::handle()` lit `ChallengeSettings::fromConfig()` | `closes_at` figé (§8.1) |
| 10 | Historique de proposition : jusqu'à 36 requêtes et lignes hydratées | `ProposeWeeklyChallenges::hasHistory()` / `weeklyValues()`, chargeurs de `GoalProgressCalculator` | une requête groupée par template (§8.2) |
| 11 | Une sortie ancienne, modifiée ensuite, sert de jeton d'antidatage réutilisable | modifier `started_at` ou `distance` garde le `created_at` d'origine, donc `created_at < closes_at` reste vrai | `moto_rides.recorded_at`, posé par le serveur à la création et à chaque changement de `started_at` ou `distance` (§4.2, §5) |
| 12 | Un décalage horaire passe la validation puis est perdu au stockage (`+14:00` : jusqu'à 14 h dans le futur, donc dans la semaine de jeu suivante ; `-14:00` en 1970 : hors de la plage `TIMESTAMP` MySQL) | `before_or_equal:now` compare des instants, le cast `datetime` formate l'heure murale du `Carbon` reçu | cast `Technical\Osdd\Casts\UtcDatetime` sur `started_at` et `executed_at` (§4.2) |
| 13 | Une chaîne de chiffres validée comme date calendaire mais stockée comme timestamp (`01800041970` : `date` la lit 1970-01-04, le cast la stockait 2027-01-15) | `date` et `before_or_equal` passent par `strtotime`, le cast `datetime` lit toute chaîne numérique comme un timestamp | forme ISO stricte `Technical\Osdd\Rules\IsoDatetime` devant `date`, et `UtcDatetime` ne lit comme timestamp que les entiers et flottants (§4.2) |
| 14 | Une valeur plus fine que la colonne est stockée 0 (vente à `unit_price` `1e-9`, `1e-400` : `gt:0` l'accepte, la colonne `decimal(18, 8)` l'arrondit à 0), et la règle `decimal` de Laravel refuse à tort les nombres JSON inférieurs à 0,0001 (`5.0E-5`) | `gt:0` et `max` comparent des valeurs exactes, pas l'échelle de stockage | règle `Technical\Osdd\Rules\WithinScale` (§4.2, §4.3) |
| 15 | Une activité synchronisée peut être supprimée (puis restaurée) par son propriétaire : la base d'un défi baisse ; supprimer puis resynchroniser levait une violation d'unicité qui interrompait tout le job | `SportActivityPolicy` hérite de `delete` / `restore` / `forceDelete` du contrôle ; `UpsertStravaActivity` ignorait les lignes supprimées | politique `false` pour les trois opérations, upsert sur `withTrashed()` qui restaure (§4.4) |
| 16 | Un lot `mutate` de milliers d'opérations tient une transaction de plusieurs dizaines de secondes ; aucune limite de débit sur `/api` | aucun plafond ni throttle sur les contrôleurs REST | 100 opérations par requête et limite nommée `rest` (§4.1) |
| 17 | L'XP finance mensuelle se fabrique avec 680 achats de 0,01 € antidatés (6 800 XP) | `FinanceMonthlyXpRule` comptait tout mois contenant un achat, sans montant ni antériorité | montant net minimum, compte et position déjà existants (§12) |

La faille 7 est nouvelle : Lomkit valide les clés d'attributs contre `fields()` puis appelle `forceFill()`. Un
`created_at` fourni reste « dirty » et Eloquent ne le remplace pas à l'insertion ; un `id` fourni en mise à jour
réécrit la clé primaire. Toute règle fondée sur `created_at` serait contournable sans ce correctif.

## 4. Règles par module

### 4.1 Principes communs

- **Identifiant et horodatages réservés au serveur.** La classe de base `Technical\Osdd\Rest\Resources\Resource` expose
  `serverManagedFieldRules(RestRequest)`, qui renvoie `['missing']` pour `id`, `created_at` et `updated_at`
  **restreints aux champs que la ressource déclare dans `fields()`** (aucune règle morte sur un champ absent). Les
  champs restent lisibles en recherche.
- **`missing` et non `prohibited`.** Sous Laravel 12.62, `prohibited` n'est que l'inverse de `required` : une valeur `null`
  ou une chaîne vide passe, puis Lomkit la `forceFill` (horodatages effacés, `id: null` en mise à jour → erreur 500).
  `missing` refuse la clé dès qu'elle est présente, `null` et chaîne vide compris ; c'est la règle de tout champ que le
  client ne doit pas envoyer (champs serveur, champs synchronisés par Strava, `completed_at` d'une tâche, champs calculés
  d'un objectif).
- **Défaut sur la classe de base.** `Resource::rules()` renvoie `serverManagedFieldRules()` : une ressource sans `rules()`
  propre (cellules explorées, ressources de gamification, en lecture seule) est protégée sans rien écrire ; une
  ressource qui définit ses règles fusionne l'assistant (`...$this->serverManagedFieldRules($request)`), ce que font
  toutes celles qui déclarent `id`, `created_at` ou `updated_at` (`moto-rides`, `investment-transactions`, `positions`,
  `tasks`, `sport-activities`, `trip-routes`, `goals`, `recurring-expenses`, `calendar-events`).
  `Tests\Feature\Lomkit\ServerManagedFieldsArchitectureTest` parcourt les ressources enregistrées sur le routeur REST
  et échoue si l'une d'elles déclare l'un de ces champs sans le protéger ; un second test vérifie que ce parcours
  découvre bien toutes les ressources routées.
- **`trip-routes`** (ressource sur le modèle `SportActivity`) garde sa route `mutate` mais n'autorise aucune écriture :
  chaque champ déclaré est `missing` (création : 422 ; tout attribut : 422 ; mutation sans attribut : 200 sans effet).
  La route reste déclarée parce que `PreventsApiCreationTest` l'attend, et elle ne peut plus réécrire une activité
  synchronisée.
- **Objectifs.** `current_value` et `progress_percentage` (calculés, sans colonne) sont déclarés dans `fields()` pour la
  lecture : `GoalResource` les met en `['missing']`, faute de quoi un client les envoyait, Lomkit les `forceFill` et la
  requête échouait en 500 (colonne inexistante).
- **Lots et débit.** `Technical\Osdd\Rest\Controllers\Controller`, parent de tous les contrôleurs REST, enregistre deux
  middlewares de contrôleur (donc avant la validation Lomkit, et sans toucher au vendor) : la limite nommée `rest`
  (`Technical\Osdd\Rest\Throttle\RestRateLimit`, 120 requêtes par minute et par utilisateur, repli sur l'adresse IP,
  429 au-delà) sur toutes les routes, et `Technical\Osdd\Rest\Middleware\LimitMutateOperations` sur la route `mutate`
  (100 opérations, imbriquées comprises ; 422 sur la clé `mutate` au-delà). Les deux valeurs sont dans
  `technical/osdd/config/osdd.php` (`rest.max_mutate_operations`, `rest.requests_per_minute`). Le middleware vit sur le
  contrôleur de base et non dans `beforeMutate`, que `RejectsApiCreation` redéfinit.
- **Dates déclarées** : `['date', 'after:1970-01-01', 'before_or_equal:now']`. Le futur est refusé ; le plancher est la
  limite basse d'une colonne `TIMESTAMP` MySQL (une date antérieure passe sous SQLite et lève une erreur 500 en
  production). Le passé reste libre : saisir une sortie d'il y a trois semaines est un usage légitime.
- **Bornes partagées** : chaque module porte une classe `src/Validation/<Modèle>Rules.php` (constantes + listes de
  règles en notation tableau) utilisée **à la fois** par la ressource REST et par le formulaire Livewire qui crée la
  même ligne, pour qu'aucune des deux portes ne diverge.
- **Nature de la source** : chaque `GoalMetric` sait si ses lignes sont saisies par l'utilisateur
  (`isSelfReported()`) ; c'est l'unique critère « synchronisé / déclaratif » du jeu (décision 9). Le seuil
  d'enregistrement, lui, n'est décidé que par la présence d'une `recordedColumn` sur l'agrégat de la métrique, et un
  test impose qu'une métrique agrégée porte cette colonne exactement quand elle est déclarative.

| Source | Écriture par l'utilisateur | Nature | Après durcissement |
|---|---|---|---|
| `sport_activities` | mise à jour API (création déjà refusée) | synchronisée Strava | seuls `name`, `sport_type` modifiables |
| `explored_cells` | création, mise à jour, suppression API | dérivée des tracés Strava | lecture seule, reconstruction convergente |
| `moto_rides` | API + formulaire `MotoDashboard` | déclarative | dates passées, bornes, horodatages serveur |
| `investment_transactions` | API + formulaire `PortfolioOverview` | déclarative | dates passées, `gt:0`, plafonds |
| `tasks` | API + `TodoBoard` | déclarative | `completed_at` posé par le serveur |
| `body_measurements` | aucune (Withings) | synchronisée | rien |
| `bank_transactions` | aucune (GoCardless) | synchronisée | rien |

### 4.2 Moto

`Functional\Moto\Validation\MotoRideRules` :

| Champ | API (`MotoRideResource`) | Livewire (`MotoDashboard::logRide`) |
|---|---|---|
| départ | `started_at` : `IsoDatetime`, `date`, `after:1970-01-01`, `before_or_equal:now` | `rideStartedAt` : `required` + mêmes règles |
| distance (km) | `distance` : `numeric`, `WithinScale(2)`, `gt:0`, `max:2000` | `rideDistance` : `required` + mêmes règles |
| durée | `duration` (s) : `integer`, `min:60`, `max:86400` | `rideDuration` (min) : `required`, `numeric`, `min:1`, `max:1440` |
| serveur | `id`, `created_at`, `updated_at` : `missing` | — (création Eloquent) |
| enregistrement | `recorded_at` : hors de `fields()` donc refusé (422) pour toute valeur, `null` et chaîne vide compris | — (non remplissable) |

2 000 km est au-delà du « SaddleSore 1000 » (1 609 km en 24 h) ; une sortie dure au plus 24 h (un voyage de plusieurs
jours se saisit par jour). La factory aligne `created_at` et `updated_at` sur `started_at` à la fabrication et `recorded_at` après la
création, par une requête de mise à jour (une sortie de factory est « saisie à temps ») ; les tests d'antidatage posent
`recorded_at` explicitement dans l'état de la factory, qui le respecte par la même mise à jour.

`moto_rides.recorded_at` est l'instant d'enregistrement, posé uniquement par le listener
`Functional\Moto\Listeners\StampRideRecording` (événement `saving`, branché par classe dans `MotoServiceProvider`) :
`now()` à toute création, même si l'appelant a posé une valeur (un `replicate()` d'une sortie ancienne, un `forceFill`),
puis `now()` à chaque sauvegarde qui change `started_at` ou `distance`. Corriger le
titre, la durée, la note ou la météo, restaurer une sortie supprimée, renvoyer les mêmes valeurs : `recorded_at` ne
bouge pas.

**Dates en UTC.** `started_at` (et `investment_transactions.executed_at`, §4.3) passent par le cast partagé
`Technical\Osdd\Casts\UtcDatetime` : toute valeur reçue (chaîne avec décalage ou identifiant de fuseau, `Carbon` ou
`DateTime` de n'importe quel fuseau, timestamp) est convertie dans le fuseau applicatif (`config('app.timezone')`, UTC)
avant stockage, et la lecture rend un `Carbon` en UTC. Sans lui, `2026-10-01T23:00:00+14:00` passait
`before_or_equal:now` (09:00 UTC, comparaison d'instants) mais était stocké `2026-10-01 23:00:00`, 14 h dans le futur,
dans la semaine de jeu suivante ; `1970-01-01T00:00:00-14:00` passait `after:1970-01-01` mais était stocké
`1970-01-01 00:00:00`, hors de la plage `TIMESTAMP` de MySQL. Avec le cast, ces deux valeurs sont stockées
`2026-10-01 09:00:00` et `1970-01-01 14:00:00`, et `2026-10-02T00:00:01+14:00` (10:00:01 UTC) reste refusé. Un `Carbon`
déjà converti dans un fuseau d'affichage (formulaire moto) est traité de même.

**Échelle des nombres.** `Technical\Osdd\Rules\WithinScale(int $scale)` refuse tout nombre qui a plus de `$scale`
décimales (2 pour la distance moto, 8 pour la quantité et le prix unitaire, l'échelle des colonnes). Les décimales sont
comptées exactement sur la représentation décimale (chaîne telle que reçue, flottant par sa plus courte représentation
aller-retour, notation exponentielle comptée arithmétiquement) et jamais par conversion en flottant : `1e-400` ou `4e-324`
devenaient `0.0`, passaient `gt:0` (comparaison exacte) et étaient stockés `0`, alors que la règle `decimal` de Laravel
refuse à tort un nombre JSON comme `0.00005` (converti en `5.0E-5`). Un fuzz de la chaîne de règles n'a trouvé aucun
contournement sur 228 000 évaluations ni aucun refus à tort sur 800 000.

**Forme ISO stricte.** La règle `Technical\Osdd\Rules\IsoDatetime` précède `date` sur `started_at` et `executed_at` :
seule une chaîne `AAAA-MM-JJ`, éventuellement suivie de `[T ]HH:MM[:SS[.fraction]]` et d'un décalage (`Z`, `+HH:MM`,
`+HHMM`) est acceptée. Elle refuse les chaînes de chiffres (`01800041970`, `202609301010`, `20260930`), les nombres JSON,
les phrases relatives (`yesterday`), les marqueurs `@timestamp` et les noms de fuseau (`Pacific/Kiritimati`) : `date`
et `before_or_equal` lisent ces valeurs comme des dates calendaires, alors que l'ancien cast stockait une chaîne
numérique comme un timestamp. Le cast `UtcDatetime` lit désormais toute chaîne comme une date (jamais comme un timestamp,
réservé aux entiers et flottants), de sorte que la valeur validée est celle qui est stockée. Un `Carbon` ou un `DateTime`
passe tel quel par le cast (la règle ne voit que les entrées du client).

### 4.3 Finance

`Functional\Finance\Validation\InvestmentTransactionRules` :

| Champ | API (`InvestmentTransactionResource`) | Livewire (`PortfolioOverview::recordTransaction`) |
|---|---|---|
| quantité | `quantity` : `numeric`, `WithinScale(8)`, `gt:0`, `max:1000000000` | `txQuantity` : `required` + mêmes règles |
| prix unitaire | `unit_price` : `numeric`, `WithinScale(8)`, `gt:0`, `max:10000000` | `txUnitPrice` : `required` + mêmes règles |
| exécution | `executed_at` : `IsoDatetime`, `date`, `after:1970-01-01`, `before_or_equal:now`, stocké en UTC (cast `UtcDatetime`, §4.2) | posé à `now()` par le composant (inchangé) |
| serveur | `id`, `created_at`, `updated_at` : `missing` | — |

Les plafonds suivent la capacité des colonnes `decimal(18, 8)` (10¹⁰) avec une marge plausible (un titre au-delà de
10 M€ l'unité n'existe pas ; 10⁹ unités couvre les cryptomonnaies à très bas prix). Ils bornent l'absurde, **pas la
triche** : un achat de 1 × 50 000 € reste valide et décroche l'Or « capital investi ». La famille finance est
déclarative ; G5 la tient hors des surfaces publiques (§7).

### 4.4 Sport (Strava)

`strava_id` et `integration_connection_id` sont `NOT NULL` et la création API est déjà refusée : toute activité est
synchronisée. `SportActivityResource::rules()` interdit donc **sans condition** (`missing`) : `id` (par
`serverManagedFieldRules()`, seul champ serveur que la ressource déclare), `strava_id`,
`distance`, `moving_time`, `elapsed_time`, `total_elevation_gain`, `average_speed`, `max_speed`, `average_heartrate`,
`max_heartrate`, `kilojoules`, `started_at`. Restent modifiables `name` (`string`, `max:255`) et `sport_type`
(`Rule::enum(SportType::class)`), qui ne nourrissent aucune mesure. Strava reste la source : la synchronisation
suivante réécrit aussi ces deux champs.

`SportActivityPolicy` renvoie `false` pour `delete`, `restore` et `forceDelete` (comme `ExploredCellPolicy`) : supprimer
une activité synchronisée ferait baisser la base d'un défi, et la restaurer ou la resynchroniser ne doit pas dépendre du
client. `UpsertStravaActivity` cherche sur `withTrashed()` : une activité supprimée localement (avant l'interdiction
de la suppression par l'API, ou à la main) est restaurée et mise à jour par la synchronisation, au lieu de lever une violation de la clé
`(integration_connection_id, strava_id)` qui interrompait `SyncStravaActivitiesJob` pour toutes les activités suivantes.

### 4.5 Exploration

Les cellules sont une **projection** des tracés des activités Strava (`RebuildUserCoverage`). Une cellule saisie à la
main est par construction une contrefaçon :

- `ExploredCellsController` utilise `RejectsApiCreation` ; `ExploredCellPolicy` renvoie `false` pour `create`, `update`
  et `delete` ; `ExploredCellResource` perd `rules()` et `createRules()`. La recherche reste ouverte au propriétaire.
- `RebuildUserCoverage::handle()` devient **convergente** : après l'upsert des cellules dérivées, elle supprime les
  cellules de l'utilisateur dont la clé n'est issue d'aucun tracé (cellules forgées avant le durcissement, cellules
  d'une activité supprimée). Les cellules conservées gardent leur `id` et leur `created_at`. Clés obsolètes calculées
  par différence sur `pluck('cell_key')`, suppression par lots de 500 (aucune liste `IN` géante).

### 4.6 Tâches

- `TaskResource::rules()` : `completed_at` → `missing` ; `id`, `created_at`, `updated_at` → `missing`.
- Listener `Functional\Todo\Listeners\StampTaskCompletion`, branché par
  `Task::saving(StampTaskCompletion::class)` dans `TodoServiceProvider::boot()` : statut `Done` sans `completed_at` →
  `now()` ; tout autre statut → `null`. Un `completed_at` déjà posé côté serveur (factory, seeder, `TodoBoard`) est
  conservé.
- Conséquence : la date de complétion est l'instant où la tâche passe à `Done`. Une série Tâches ne peut plus être
  fabriquée d'un coup ; elle demande une complétion réelle par jour.

## 5. Antidatage : la règle d'enregistrement à temps

### 5.1 Définition

L'**instant de clôture** d'une semaine de jeu W est `closes_at(W) = W.ends_at + closing_grace_hours` (48 h par défaut),
porté par `GamificationWeek::closesAt(int $graceHours)` et figé sur chaque défi (§8.1).

> Une ligne **déclarative** (métrique `isSelfReported()`) datée dans W ne compte pour W que si elle a été enregistrée
> avant la clôture de W : `recorded_at < closes_at(W)`.

Pour une sortie moto, `recorded_at` est l'instant d'enregistrement du §4.2, **pas `created_at`** : modifier `started_at`
ou `distance` d'une sortie existante repose `recorded_at`, alors que `created_at` ne bouge jamais. Avec `created_at`,
toute sortie ancienne servait de jeton réutilisable : redatée dans une semaine déjà close, elle gardait un `created_at`
antérieur à la clôture et comptait. Avec `recorded_at`, une sortie redatée (ou dont la distance est corrigée) après la
clôture de W ne compte plus pour W, et ne peut compter que pour une semaine dont la clôture n'est pas passée ; une
correction légitime reste possible, elle perd seulement ses effets ludiques sur les semaines closes.

Les métriques synchronisées (les quatre métriques sport, `exploration_cells`) comptent sur leur date déclarée quel que
soit leur instant d'enregistrement : un backfill Strava du mercredi reste valable pour l'historique (G4 §10).

| Métrique | Source | `isSelfReported()` |
|---|---|---|
| `sport_distance`, `sport_elevation`, `sport_activity_count`, `sport_moving_time` | Strava | non |
| `exploration_cells` | tracés Strava | non |
| `moto_distance`, `moto_ride_count` | saisie | oui |
| `finance_invested_capital`, `finance_portfolio_value`, `manual`, `todo_completion_rate` | saisie | oui |

### 5.2 Où elle s'applique

- **Historique de proposition** (éligibilité et médiane) : chaque semaine W−4 … W−1 est mesurée avec
  `recordedBefore = closes_at(W−k)`, la grâce étant celle de la config au moment de la proposition (celle qui sera
  figée sur les nouveaux défis).
- **Progression et verdict d'un défi** : la mesure de `[starts_at, ends_at[` exclut les lignes déclaratives enregistrées à
  partir de `closes_at`. Le verdict d'un défi moto ne dépend donc plus de l'instant du premier passage qui suit la
  grâce ; pour les métriques synchronisées, la règle G4 est inchangée (une activité de la semaine synchronisée avant ce
  passage compte encore).

Elle ne s'applique **pas** à l'XP de base, aux séries, aux badges ni aux Objectifs (décision 8).

### 5.3 Exemples exacts

Proposition de 2026-W40 au passage du lundi 2026-09-28 06:00 UTC, grâce 48 h. Clôtures de l'historique : W36
`2026-09-08 22:00:00`, W37 `2026-09-15 22:00:00`, W38 `2026-09-22 22:00:00`, W39 `2026-09-29 22:00:00` (UTC).

- Sorties de 100 / 120 / 140 / 160 km les 02/09, 09/09, 16/09, 23/09 à 12:00, chacune enregistrée le jour même → historique
  `[100, 120, 140, 160]`, base 130, cible 150 (G4).
- Même historique, sortie du 16/09 enregistrée le `2026-09-22 22:00:00` → historique `[100, 120, 0, 160]`, base 110,
  cible 130 ; enregistrée à `21:59:59` → compte, base 130, cible 150.
- Sortie du 23/09 enregistrée le `2026-09-28 05:00:00` (après la fin de W39, dans sa grâce) → compte.
- **Sortie corrigée après la clôture** : une sortie du 16/09 saisie à temps (`recorded_at` le 16/09) puis dont la
  distance est corrigée le `2026-09-30` reçoit `recorded_at` `2026-09-30 …` : elle ne compte plus pour W38 (clôturée le
  `2026-09-22 22:00:00`). Changer seulement son titre ou sa note ne change rien.
- **Domaine découvert la veille** : au passage du 28/09, un utilisateur sans historique moto saisit des sorties datées du
  16/09 et du 23/09 (`recorded_at` le 28/09) → W38 hors délai, W39 à temps : une seule semaine active sur deux exigées, aucun défi moto. Être
  éligible exige désormais d'avoir saisi pendant deux semaines distinctes, chacune avant sa clôture.
- Activités Strava de 10 / 20 / 30 / 40 km datées W36–W39, toutes synchronisées le `2026-09-28 05:00:00` (backfill) → défi
  `sport_distance` base 25, cible 28 (G4, inchangé).
- Défi `moto_distance` W40 accepté, cible 150, `closes_at` `2026-10-06 22:00:00` : sortie de 160 km datée du 04/10
  enregistrée à `2026-10-06 21:59:59` → `Completed` ; enregistrée à `22:00:00` → ignorée, `Failed` au passage de `22:00:00` avec
  `current_value` `0.00`.

## 6. Provenance

### 6.1 Badges

Colonne `badge_awards.measured_value` (`double`, nullable) : la mesure de la règle au passage qui a créé l'attribution,
arrondie à deux décimales. Jamais mise à jour ensuite (le badge est définitif, son instantané aussi). `null` = attribution
antérieure au durcissement : aucune reconstitution, car la mesure d'aujourd'hui n'est pas celle du jour de
l'attribution. Non exposée par l'API `badge-awards` (donnée d'audit ; G5 n'expose jamais de donnée brute).

Danger nommé : en `decimal(14, 2)`, une mesure déclarative absurde (somme de transactions plafonnées : jusqu'à 10¹⁶ €
par ligne) déborderait sous MySQL strict et ferait échouer toute la transaction de `RunUserGamification` ; un `double`
ne déborde pas.

### 6.2 Défis

Rien à ajouter : `baseline_value` et `target_value` sont figées à la proposition, `current_value` est figée par
`TransitionChallenge` à l'état terminal, `closes_at` fige la clôture, et les sorties moto, activités, transactions et
tâches sont en `SoftDeletes` : une preuve supprimée reste en base. Un audit recalcule la mesure de
`[starts_at, ends_at[` avec `withTrashed()` et la règle du §5 et la compare à `current_value`.

## 7. Règle d'XP du classement G5 (décidée ici, implémentée en G5)

**Le classement ne compte que l'XP de base adossée à une source synchronisée** : somme des `points` des entrées
`xp_entries` dont la clé est `sport_activity` (Strava), `health_measurement_day` (Withings) ou
`exploration_daily_cells` (tracés Strava), sur la période affichée (semaine de jeu ISO, ou mois), découpée sur
`occurred_at` dans le fuseau de jeu.

| Clé | Source | Compte au classement |
|---|---|---|
| `sport_activity`, `health_measurement_day`, `exploration_daily_cells` | synchronisée | oui |
| `moto_ride`, `todo_task_completed` | saisie | non |
| `finance_month` | épargne bancaire et apports saisis mêlés | non |
| `streak_milestone`, `badge_award`, `challenge_completed` | bonus | non |

G5 portera la décision par une méthode d'enum (`XpRuleKey::countsTowardsLeaderboard()`) ; elle n'est pas créée ici
faute d'appelant.

« Synchronisée » n'est pas « vérifiée » : une pesée Withings saisie à la main, un téléversement ou une activité Strava
manuelle, un même athlète relié à plusieurs comptes, une activité ancienne importée après coup alimentent les trois clés
du classement. Les pré-requis qui comblent ces écarts sont décidés au §16 et conditionnent G5.

Orientations pour G5, à confirmer à sa conception :

- une surface publique ne montre jamais un chiffre que la règle exclut : XP et niveau publics calculés sur la même XP
  vérifiée ; séries publiques limitées aux domaines Sport, Santé et Exploration ; badges publics limités aux familles
  `sport_*`, `health_*`, `exploration_cells` ; le hub privé continue de tout montrer ;
- une attribution à `measured_value` nul (antérieure au durcissement) n'est affichée publiquement que si la mesure
  actuelle atteint encore le seuil ;
- les défis entre joueurs ne portent que sur des métriques `! isSelfReported()`.

## 8. Suivis G4

### 8.1 Clôture figée par défi

Nouvelle colonne `challenges.closes_at` (timestamp, non nul, après `ends_at`) = `ends_at + closing_grace_hours` lu à la
proposition. `ResolveChallenges` ne lit plus la config : `gracePassed = now ≥ closes_at`. `GamificationWeek::isPastGrace()`
disparaît au profit de `closesAt(int $graceHours)`. Une nouvelle valeur de `closing_grace_hours` ne s'applique qu'aux
défis proposés ensuite, comme le promettait G4 §10.

Migration : colonne ajoutée nullable, backfill **en PHP par valeur distincte de `ends_at`** (au plus une par semaine
écoulée depuis G4) avec la grâce de la config validée par `ChallengeSettings::fromConfig()` au moment de la migration —
celle que ces défis auraient reçue à la résolution —, puis passage en non nul (`->change()`). Aucune arithmétique de
date en SQL (`DATE_ADD` et `datetime()` ne sont pas portables). Non exposée par l'API `challenges` (aucun client).

### 8.2 Historique agrégé en SQL

Nouveau service `Functional\Goals\Services\GoalMetricAggregator` (layer Objectifs, seul détenteur de la façon
d'agréger une métrique, pour que « 28 km » reste identique sur les deux écrans) :

- `total(metric, userId, from, until)` : bornes **inclusives**, une agrégation `sum` / `count` sur le modèle ;
  `GoalProgressCalculator::measure()` lui délègue les sept métriques sport, moto et exploration, ce qui supprime
  l'hydratation des lignes et les dépendances `SportStatisticsCalculator` / `RidingStatsCalculator`. `currentValue(Goal)`
  garde ses valeurs (mêmes bornes, mêmes sommes). `FinanceInvestedCapital` reste calculé par `CapitalCalculator`
  (règle du module finance).
- `totalsPerPeriod(metric, userId, periods)` : périodes **demi-ouvertes** `MeasurementPeriod(startsAt, endsAt,
  ?recordedBefore)`, **une seule requête groupée** pour toutes les périodes, sans hydratation :

```sql
select case
         when started_at >= ? and started_at < ? and recorded_at < ? then 0
         when started_at >= ? and started_at < ? and recorded_at < ? then 1
         when started_at >= ? and started_at < ? and recorded_at < ? then 2
         when started_at >= ? and started_at < ? and recorded_at < ? then 3
       end as period_index,
       sum(distance) as total
from moto_rides
where user_id = ? and started_at >= ? and started_at < ? and deleted_at is null
group by period_index
```

Les seaux sont des `CASE` à bornes liées, donc portables MySQL / SQLite (aucun `strftime`, `YEARWEEK`, `CONVERT_TZ`) ;
toutes les bornes sont converties dans le fuseau applicatif (UTC) avant liaison ; les colonnes sont entourées par la
grammaire du builder ; les lignes d'index nul (déclaratives hors délai) sont ignorées ; les périodes sans ligne valent
`0.0`. Le terme `recorded_at < ?` n'apparaît que pour une période qui porte `recordedBefore` ; seule la table
`moto_rides` porte cette colonne, les métriques synchronisées ne sont jamais filtrées.

| Métrique | Modèle | Colonne de date | Agrégat |
|---|---|---|---|
| `sport_distance` | `SportActivity` | `started_at` | `sum(distance) / 1000` |
| `sport_elevation` | `SportActivity` | `started_at` | `sum(total_elevation_gain)` |
| `sport_activity_count` | `SportActivity` | `started_at` | `count(*)` |
| `sport_moving_time` | `SportActivity` | `started_at` | `sum(moving_time) / 3600` |
| `moto_distance` | `MotoRide` | `started_at` | `sum(distance)` |
| `moto_ride_count` | `MotoRide` | `started_at` | `count(*)` |
| `exploration_cells` | `ExploredCell` | `first_seen_at` | `count(*)` |

Côté gamification, `WeeklyMetricMeter::history(user, metric, weeks, graceHours)` construit les quatre périodes (chacune
avec `recordedBefore = closes_at`, pour toute métrique : seule la colonne d'enregistrement de l'agrégat,
`MetricAggregate::$recordedColumn`, décide si le seuil s'applique) et `WeeklyMetricMeter::measure(user, metric, startsAt, endsAt,
closesAt)` mesure un défi. `ProposeWeeklyChallenges` perd `hasHistory()` : le calculateur de cible écarte déjà un
historique sans semaine active.

| Premier passage d'une semaine | Avant | Après |
|---|---|---|
| utilisateur sans donnée | 8 requêtes | 8 (1 existence + 7 historiques) |
| historique sport + moto + exploration | jusqu'à 36 + 2 | 10 (1 + 7 + insertion + relecture) |

## 9. Modèle de données

| Table | Changement | Migration |
|---|---|---|
| `challenges` | `closes_at` timestamp non nul, après `ends_at` | `functional/gamification/database/migrations/2026_10_05_000001_add_closes_at_to_challenges_table.php` (nullable → backfill → non nul ; `down` supprime la colonne) |
| `badge_awards` | `measured_value` double nullable, après `awarded_at` | `functional/gamification/database/migrations/2026_10_05_000002_add_measured_value_to_badge_awards_table.php` (sans backfill) |
| `moto_rides` | `recorded_at` timestamp non nul, après `created_at` | `functional/moto/database/migrations/2026_10_05_000003_add_recorded_at_to_moto_rides_table.php` (nullable, ajoutée seulement si absente → backfill `recorded_at = created_at` des seules lignes encore nulles, puis l'instant de la migration pour une ligne sans `created_at` → non nul, ré-exécutable après une exécution interrompue ; `down` supprime la colonne) |

Aucune autre table ne change : `sport_activities`, `explored_cells`, `investment_transactions` et `tasks`
ont déjà des `timestamps()` fiables une fois la faille 7 fermée. Aucun identifiant nouveau (les ULID existants restent),
aucune clé étrangère, aucun enum SQL, aucun SQL propre à SQLite.

## 10. Flux

- **Mutation REST** (sortie, transaction, tâche) : limite de débit `rest` → plafond de 100 opérations → validation Lomkit (`rules()` + `serverManagedFieldRules()`) →
  `forceFill` des seuls champs autorisés → `creating` (propriétaire) → `saving` (`StampTaskCompletion` pour une tâche, `StampRideRecording` pour une sortie)
  → `created_at` posé par Eloquent.
- **Formulaire Livewire** : mêmes bornes via la classe `Validation` du module, création Eloquent.
- **Couverture** : `RebuildUserCoverage` agrège les tracés, upserte, supprime les cellules non dérivées ; lancée par
  `RebuildCoverageJob`, elle est suivie de l'événement `CoverageRebuilt` qui relance la gamification (inchangé).
- **Proposition** : test d'existence de l'ensemble → pour chaque template, `history()` (une requête) →
  `ChallengeTargetCalculator::target()` → lignes avec `closes_at = week.closesAt(grace)` → `insertOrIgnore`.
- **Résolution** : pour chaque défi ouvert, `measure(…, closes_at)` → `ChallengeProgress(targetReached,
  weekEnded: now ≥ ends_at, gracePassed: now ≥ closes_at)` → `evolve()` → `TransitionChallenge` (inchangé).
- **Badges** : mesures des règles → insertion des attributions avec `measured_value` → reconvergence du ledger
  (inchangée).

## 11. Cas limites

- **Horloge client en avance** : un `started_at` d'une seconde dans le futur est refusé (422) ; le client renvoie.
- **Formulaire moto en fuseau applicatif** : `rideStartedAt` est saisi et interprété en UTC (comportement existant) et
  `before_or_equal:now` compare dans le même fuseau. Un utilisateur qui taperait l'heure de Paris dans les deux heures
  précédant « maintenant » serait refusé ; l'ambiguïté de fuseau du formulaire est antérieure et hors périmètre. Une valeur saisie avec un décalage explicite est,
  elle, convertie en UTC avant stockage (§4.2).
- **Sortie déplacée ou corrigée** : modifier `started_at` ou `distance` repose `recorded_at` à l'instant de la
  modification, donc la sortie ne compte pas pour une semaine déjà close (même si elle y avait été saisie à temps) ; la
  déplacer dans la semaine en cours la fait compter. Modifier le titre, la durée, la note ou la météo n'y change rien.
- **Sortie restaurée** (`restore` Lomkit) : `recorded_at` d'origine, même règle.
- **Sorties existantes à la migration** : `recorded_at` reprend leur `created_at` (une ligne sans `created_at`, possible
  seulement par la faille 7 avant correctif, prend l'instant de la migration et compte donc comme tardive).
- **Antidatage dans la semaine en cours** : une sortie enregistrée jeudi et datée de mardi compte (saisie à temps) ; risque
  accepté (§12).
- **Changement de `closing_grace_hours`** : s'applique aux défis proposés ensuite et aux semaines d'historique de la
  prochaine proposition ; les défis existants gardent leur `closes_at`.
- **Heure d'hiver / d'été** : `closes_at` = `ends_at` (instant UTC) + 48 h réelles ; W43 se clôt le
  `2026-10-27 23:00:00` UTC.
- **Tâche rouverte puis refermée** : `completed_at` repasse à `null` puis prend l'instant de la nouvelle complétion ;
  l'XP de la tâche se date à ce nouvel instant.
- **Client API qui envoyait `completed_at`** : 422 ; il doit envoyer le seul `status`.
- **Cellules forgées existantes** : supprimées à la première reconstruction (bouton « Recalculer » de l'écran
  Exploration, ou `exploration:rebuild-coverage` à lancer une fois sur le serveur après la livraison) ; leurs badges
  restent (définitifs) ; leur XP disparaît au prochain passage complet (backfill hebdomadaire du dimanche 03:00 ou
  `gamification:recalculate`).
- **Distance Strava modifiée avant le durcissement** : la valeur reste en base jusqu'à ce que Strava renvoie l'activité ;
  l'attribution qu'elle a pu valoir a `measured_value` nul (§7, orientations).
- **Vente à 0,01 €** : acceptée (plausible) ; la finance reste déclarative.
- **Utilisateur supprimé** : aucune donnée nouvelle à purger (colonnes sur des tables déjà purgées par
  `DeleteUserGamificationData`).

## 12. Risques résiduels acceptés

- **Données déclaratives de la semaine en cours** : créer une fausse sortie, valider le défi puis la supprimer garde
  50 XP ; borné à 50 XP par domaine et par semaine, auditable (§6.2), exclu du classement.
- **Badges des familles déclaratives** : plusieurs lignes plausibles suffisent (10 sorties de 2 000 km pour l'Or moto,
  un achat de 50 000 € pour l'Or finance, 1 000 tâches passées à `Done`, ce que le plafond de 100 opérations par requête
  et les 120 requêtes par minute ralentissent sans l'empêcher) ; hub privé seulement, hors surfaces publiques G5.
- **Séries moto antidatées** : 100 sorties sur 100 jours passés valent 525 XP de paliers ; hub privé seulement.
- **Activités Strava manuelles** : déclaratives côté Strava, indiscernables sans le champ `manual` du SDK ; comptées
  comme synchronisées. Si le SDK `foutraz/strava` l'expose un jour, G5 pourra les exclure.
- **XP finance mensuelle (décision de Quentin, 2026-10-05)** : la part « apport » de `finance_month` (10 XP) ne compte un
  mois que si le montant net acheté de ce mois (achats moins ventes, `quantité × prix unitaire`) atteint
  `gamification.xp.finance.investment_minimum_net_bought` (10 € par défaut, configuration validée par
  `FinanceXpSettings`, strictement positive) et si ce mois n'est antérieur ni au mois de création du compte ni à celui de la
  position de la transaction (chaque transaction est écartée individuellement). Sans ce garde-fou, 680 achats de 0,01 €
  antidatés valaient 6 800 XP. La part « épargne » (relevés GoCardless, non saisissables) est inchangée, y compris pour un
  mois antérieur au compte. Un historique saisi après coup reste visible partout, il ne rapporte simplement pas d'XP ;
  l'XP déjà versée pour un mois désormais exclu disparaît au prochain passage complet (`gamification:recalculate`).
  Risque résiduel accepté : 10 € nets achetés chaque mois depuis l'ouverture du compte valent 10 XP par mois ; hub privé
  seulement, `finance_month` reste hors du classement G5 (§7).
- **`UserResource`** : elle déclare un champ `ulid` alors que la clé primaire est `id` (toute écriture, filtre ou tri sur
  `ulid` échoue en 500) et n'utilise pas l'assistant du §4.1 ; elle ne déclare ni `id` ni horodatages, donc aucun champ
  serveur n'est inscriptible. Correction à part (déclarer `id`, fusionner l'assistant), hors du jeu.

## 13. Tests

TDD par tâche, PHPUnit en classes, `#[Test]`, noms `it_...`, factories, `travelTo(Carbon::parse('…', 'UTC'))`.

- Moto : `tests/Feature/Moto/MotoRideValidationTest.php` (bornes, futur, plancher, `created_at` / `id` interdits,
  `created_at` serveur), `tests/Feature/Moto/RideRecordingStampTest.php` et `RecordedAtMigrationTest.php`
  (`recorded_at`), `tests/Feature/Moto/RideDateTimezoneTest.php` (décalages, API et Livewire), ajouts dans
  `MotoDashboardComponentTest`.
- Finance : ajouts dans `InvestmentTransactionValidationTest` et `PortfolioOverviewComponentTest`,
  `tests/Feature/Finance/TransactionDateTimezoneTest.php` (décalages).
- Cast et règle : `tests/Unit/Osdd/UtcDatetimeTest.php`, `tests/Unit/Osdd/IsoDatetimeTest.php`.
- Tâches : `tests/Feature/Todo/TaskCompletionStampTest.php`, ajouts dans `TaskValidationTest`.
- Sport / exploration : ajouts dans `ActivitiesApiScopeTest` (suppression, restauration et suppression définitive
  refusées au propriétaire), `SyncActivitiesJobTest` (activité supprimée restaurée, le job continue),
  `ExploredCellsApiScopeTest`, `PreventsApiCreationTest`, `RebuildCoverageTest`.
- API REST commune : `tests/Feature/Lomkit/MutateOperationsCapTest.php` (plafond, imbriqué, configurable, présent sur
  toutes les routes `mutate`), `RestRateLimitTest.php` (429, seaux par utilisateur, repli IP, présent sur toutes les
  routes), `ServerManagedFieldsArchitectureTest.php`.
- Objectifs : `tests/Feature/Goals/GoalComputedFieldsTest.php` (champs calculés refusés en création et en mise à jour).
- Objectifs : `tests/Feature/Goals/GoalMetricAggregatorTest.php`, ajouts dans `tests/Unit/Goals/GoalProgressCalculatorTest.php` ;
  tous les tests Objectifs existants restent verts sans modification.
- Gamification : `tests/Feature/Gamification/ChallengeClosesAtMigrationTest.php`,
  `FinanceMonthInvestmentXpTest.php` et `FinanceXpSettingsTest.php` (XP finance mensuelle), ajouts dans `GamificationWeekTest`,
  `ChallengeModelTest`, `ProposeWeeklyChallengesTest`, `ResolveChallengesTest`, `WeeklyMetricMeterTest`,
  `EvaluateBadgesTest`, `BadgeModelTest`, `GamificationApiScopeTest`, `WeeklyMetricMeterRecordingCutoffTest` (le seuil
  est transmis pour toute métrique, et appliqué exactement quand elle est déclarative).
- Tests existants adaptés, et eux seuls : `ExploredCellsApiScopeTest::it_allows_the_owner_to_update_their_explored_cell`
  (le comportement s'inverse), `RunChallengeCycleTest::it_reads_the_closing_grace_on_every_call` (idem),
  `GamificationWeekTest` (`isPastGrace` remplacé par `closesAt`), `WeeklyMetricMeterTest` (signature),
  `ProposeWeeklyChallengesTest::it_measures_the_weeks_of_the_templates_with_data_only` (budget de requêtes),
  `XpRulesTest::it_adds_an_investment_bonus_to_the_month` et
  `ProcessUserGamificationJobTest::it_updates_a_period_award_when_the_month_data_changes` (le compte et la position
  doivent exister avant le mois de l'achat pour que la part « apport » compte, §12).

## 14. Décisions prises

0. **Alignement G1–G4** : validation dans les modules qui possèdent les données, règles de jeu dans la gamification,
   agrégation dans les Objectifs, aucune écriture d'XP hors du ledger, aucune dépendance nouvelle.
1. **Bornes portées par une classe `Validation` par module, partagée entre REST et Livewire.** Rejeté : `FormRequest`
   (Lomkit porte la validation des ressources) ; règles recopiées dans la ressource et le composant (c'est cette
   divergence qui a laissé passer les failles) ; une règle commune dans un layer technique (couplage inter-layers pour
   trois lignes).
2. **Identifiant et horodatages en `missing`, via `serverManagedFieldRules()` restreint aux champs déclarés, par défaut
   sur la classe de base.**
   Rejeté : `prohibited` (laisse passer `null` et chaîne vide, que Lomkit `forceFill` ensuite) ; retirer `id` /
   `created_at` de `fields()` (casse la recherche et le tri des clients) ; les réinitialiser dans le hook `mutating` de la
   ressource (réparation silencieuse, le client croit avoir écrit) ; l'appliquer ressource par ressource sans défaut ni
   test d'architecture (la revue de sécurité a trouvé `trip-routes` oubliée, avec une écriture complète sur les activités
   synchronisées).
3. **Dates : `before_or_equal:now` et `after:1970-01-01`, passé libre.** Rejeté : une tolérance de quelques minutes
   (aucun client ne la demande) ; une fenêtre d'antidatage (7 ou 30 jours) pour les saisies (interdirait de consigner
   une sortie ancienne, contraire au suivi d'abord).
4. **Plafonds : 2 000 km et 24 h par sortie ; quantité ≤ 10⁹, prix unitaire ≤ 10⁷, tous deux `gt:0`.** Rejeté : plafond
   du montant `quantity × unit_price` et vitesse moyenne plausible (règles croisées que Lomkit ne peut évaluer sur une
   mise à jour partielle sans la ligne stockée) ; des plafonds assez bas pour qu'une ligne n'atteigne jamais l'Or
   (un achat de 50 000 € est plausible).
5. **Champs Strava interdits sans condition, `name` et `sport_type` modifiables.** Rejeté : `prohibited_if` sur
   `strava_id` (colonne non nulle : garde qui ne peut jamais être fausse) ; API sport entièrement en lecture seule
   (corriger un nom ou un type est légitime et sans effet sur le jeu).
6. **Cellules explorées en lecture seule, reconstruction convergente.** Rejeté : garder la création en la validant (une
   projection ne se saisit pas) ; lecture seule sans convergence (les cellules forgées compteraient à jamais).
7. **`completed_at` posé par le serveur au passage à `Done`.** Rejeté : `before_or_equal:now` + `after_or_equal` la
   création de la tâche (règle croisée avec la ligne stockée, et l'heure resterait au choix du client) ; laisser le
   champ libre.
8. **Antidatage traité par l'enregistrement avant la clôture de la semaine, pour les métriques déclaratives, dans
   l'historique et la progression des défis seulement.** L'instant d'enregistrement d'une sortie est `moto_rides.recorded_at`,
   re-posé par le serveur quand `started_at` ou `distance` change. Rejeté : appliquer `created_at` à toutes les sources
   (casse le backfill Strava de G4 §10) ; utiliser `created_at` pour la moto (une sortie ancienne modifiée garde son
   `created_at` : jeton d'antidatage réutilisable) ; interdire de modifier `started_at` / `distance` (une correction
   légitime doit rester possible) ; compter les lignes à leur `created_at` (déplace une saisie tardive légitime dans la
   mauvaise semaine) ; borner l'antidatage à la saisie (suivi) ; l'appliquer à l'XP de base, aux séries et aux badges
   (il faudrait une colonne d'enregistrement sur `xp_entries` pour un seul domaine ; les badges cumulent sans date ; G5
   tient le déclaratif hors du public).
9. **Nature de la source par métrique (`GoalMetric::isSelfReported()`), pas par ligne.** Aucune table ne mêle
   aujourd'hui les deux natures ; une colonne `source` ne serait jamais lue différemment. Rejeté : colonne `source` par
   ligne (à ajouter le jour où une synchronisation moto, Liberty Rider par exemple, arrivera) ; une liste de modèles de
   confiance dans la gamification (connaissance des sources hors du layer Objectifs qui les agrège déjà).
10. **`measured_value` double nullable, sans backfill, non exposé.** Rejeté : `decimal(14, 2)` (débordement MySQL qui
    ferait échouer tout le passage) ; backfill avec la mesure actuelle (instantané fabriqué) ; liste des lignes
    preuves (lourd, les `SoftDeletes` gardent déjà les preuves) ; exposition API (donnée d'audit).
11. **Défis : `current_value` figée suffit.** Rejeté : une colonne de provenance sur `challenges` (redondante avec les
    valeurs figées, `closes_at` et les preuves conservées).
12. **Classement G5 sur la seule XP de base synchronisée.** Rejeté : toute l'XP (lignes fabriquées) ; exclure seulement
    les clés bonus (l'XP de base déclarative reste extensible : 35 XP par fausse sortie, sans plafond quotidien) ;
    inclure les bonus des domaines synchronisés (un badge Or de 500 XP écrase une semaine, les cibles de défis sont
    relatives à chacun).
13. **Instant `closes_at` sur la ligne plutôt qu'un nombre d'heures.** Rejeté : `closing_grace_hours` par ligne (défaut
    de colonne qui doublonne la config, recalcul à chaque lecture) ; continuer à lire la config (contredit G4 §10).
14. **Backfill de `closes_at` en PHP par valeur distincte de `ends_at`, grâce de la config validée.** Rejeté :
    arithmétique de date en SQL (non portable) ; un 48 en dur (ignorerait une config déjà modifiée) ; chargement des
    lignes une à une.
15. **Agrégation dans le layer Objectifs, une requête groupée par template, seaux en `CASE` liés.** Rejeté :
    `YEARWEEK` / `strftime` / `CONVERT_TZ` (non portables, fuseau impossible) ; une requête par semaine (statu quo) ;
    une requête par table source pour plusieurs templates (couple les templates entre eux pour gagner quatre
    requêtes) ; réimplémenter l'agrégat dans la gamification (divergence Objectifs / défis) ; agréger
    `FinanceInvestedCapital` en SQL (recopie la règle de `CapitalCalculator`).
16. **Tests G4 adaptés, limités à la liste du §13.** Ce sont exactement les comportements que ce lot change ; tout autre
    test existant reste vert sans modification.
17. **Dates normalisées en UTC par un cast partagé (`UtcDatetime`).** Rejeté : convertir dans la règle de validation (la
    valeur validée n'est pas celle qui est stockée) ; un mutateur par modèle (copié deux fois, oubliable sur le suivant) ;
    refuser les valeurs avec décalage (casse les clients légitimes qui envoient de l'ISO 8601 avec offset). La validation
    impose en plus une forme ISO stricte (`IsoDatetime`) : rejeté, garder `date` seule (elle accepte des chaînes de
    chiffres, des phrases relatives et des noms de fuseau que le stockage ne lit pas comme la validation) ; accepter les
    noms de fuseau (aucun client n'en envoie, un décalage numérique suffit).
18. **XP finance mensuelle : montant net minimum et antériorité du compte et de la position (décision de Quentin).**
    Rejeté : un plafond du nombre de mois par utilisateur (un historique réel plus long serait pénalisé) ; supprimer la
    part « apport » (elle récompense un geste réel) ; appliquer l'antériorité à la part « épargne » (les relevés bancaires
    d'avant l'ouverture du compte sont légitimes et non saisissables) ; un minimum en pourcentage du capital (dépend d'un
    montant déclaratif).

## 15. Revues de sécurité et issues

Trois revues de sécurité ont suivi l'implémentation ; chaque correction est épinglée par un test et revérifiée.

**Revue des validations (tâches 1 à 4).**

| Constat | Issue |
|---|---|
| Critique : `trip-routes` écrivait sur `sport_activities` sans garde (distance, `started_at`, tracé : 6 badges et de l'XP avec un tracé forgé) | tour A : route en écriture impossible, `Resource::rules()` par défaut et test d'architecture sur toutes les ressources (§4.1) |
| Élevé : modifier `started_at` ou `distance` d'une sortie gardait son `created_at`, donc un jeton d'antidatage réutilisable | tour B : `moto_rides.recorded_at` posé par `StampRideRecording`, base de la règle d'enregistrement à temps (§4.2, §5) |
| Moyen : décalage horaire (`+14:00`) stocké en heure murale, jusqu'à 14 h dans le futur | tour B : cast `UtcDatetime` sur `started_at` et `executed_at` (§4.2) |
| Faible : l'assistant des champs serveur était optionnel par ressource | tour A : défaut sur la classe de base (§4.1) |
| Faible : 1 000 tâches `Done` en une requête valent trois badges Tâches | accepté, borné par le plafond de lot (§12) |
| Faible : `1e999` ou une chaîne commençant par un saut de page fait lever une exception aux règles `gt` / `max` (500, échec fermé, comportement antérieur) | non traité, sans effet sur le jeu |

**Revue du tour B.** Une chaîne de chiffres (`01800041970`) passait `date` comme date calendaire et était stockée comme
timestamp (jusqu'en 2038) ; le fuseau de session MySQL n'était pas fixé ; la migration `recorded_at` n'était pas ré-exécutable
après une interruption. Tour C : forme ISO stricte `IsoDatetime` devant `date`, `UtcDatetime` qui lit toute chaîne comme une
date, `recorded_at` posé inconditionnellement à la création (un `replicate()` ne porte plus un ancien instant),
`DB_TIMEZONE` figé à `+00:00` dans la connexion `mysql`, migration ré-exécutable (`hasColumn`). Accepté : la reprise des
`created_at` historiques par le backfill, les mises à jour sans modèle (`increment`, requêtes directes) qui ne reposent
pas `recorded_at` (aucun appelant).

**Revue de la tâche 2.** `WithinScale` (§4.2) est le résultat de trois tours : `decimal:0,N` refusait à tort les petits
nombres JSON, puis le repli sur un flottant laissait passer `1e-400` ; le compte exact des décimales clôt le sujet.

**Revue finale.** Aucun constat critique ni élevé. Moyen : « synchronisé » n'est pas « vérifié » (§16). Faibles :
activités synchronisées supprimables (§4.4), XP finance fabricable (§12), données forgées avant le durcissement qui
nourrissent encore les clés G5, absence de plafond de lot et de limite de débit (§4.1).

**Livraison.** `DB_TIMEZONE=+00:00` change l'interprétation des colonnes `TIMESTAMP` existantes si la session MySQL de
production tournait sur un autre fuseau (décalage de 1 à 2 h pour `Europe/Paris`) : exécuter
`SELECT @@global.time_zone, @@system_time_zone;` sur la production **avant** de livrer, et, hors UTC, planifier une
conversion unique ou accepter le décalage ; étape bloquante de la pull request. Après la livraison : une synchronisation
Strava complète (réécrit les valeurs d'activités forgées avant le durcissement), `exploration:rebuild-coverage`, puis
`gamification:recalculate` (retire l'XP des cellules et des mois finance forgés).

## 16. Pré-requis G5 (décidés par Quentin, 2026-10-05)

Décision : durcir les sources synchronisées **avant** G5, dans un chantier séparé qui suit la livraison de cette branche
et de `fix/post-gamification-followups`. La spécification G5 reprend ces quatre points comme entrées de conception
obligatoires ; aucun n'est livré ici, les changements de SDK étant hors périmètre de cette branche.

1. **Withings : distinguer le mesuré du saisi.** `foutraz/withings` doit exposer l'attribution (`attrib`) et la catégorie
   (`category`) de chaque groupe de mesures ; une pesée saisie à la main (attribution 2) ne compte ni pour
   `health_measurement_day` au classement, ni pour les séries et badges publics.
2. **Strava : distinguer l'activité enregistrée de la saisie.** `foutraz/strava` doit exposer les indicateurs de saisie manuelle
   (`manual`) et de téléversement de l'API Strava ; l'activité manuelle ou téléversée ne compte pas pour `sport_activity` ni
   `exploration_daily_cells` au classement (aujourd'hui acceptée comme synchronisée, §12).
3. **Un compte fournisseur pour un compte du tableau de bord.** Unicité `(fournisseur, identifiant externe)` sur les
   connexions d'intégration, vérifiée à la liaison : un même compte Strava ou Withings ne peut alimenter qu'un seul
   joueur.
4. **Périodes de classement figées à la clôture.** Le classement d'une période close est écrit une fois à sa clôture ;
   une activité ancienne importée ensuite (synchronisation tardive, backfill) ne déplace plus un classement passé.

Tant que ces quatre points ne sont pas livrés, la règle du §7 reste une décision de principe : aucune surface publique
ne s'ouvre.

# Roadmap stratégique — Interconnexions & intégrations

> Feuille de route priorisée par **valeur « dashboard central »**. Objectif : faire réellement converger les modules existants vers l'accueil, puis brancher de nouvelles intégrations qui approfondissent cette vue. À dérouler **une phase par cycle** (spec → plan → implémentation), une branche par chantier.

---

## Contexte & problème

L'application a livré 8 modules fonctionnels (`sport`, `todo`, `finance`, `goals`, `recurring-expenses`, `exploration`, `moto`, `planning`) + couches techniques (`osdd`, `integrations`, `web-authentication`…) sous architecture OSDD, avec 4 SDK Foutraz opérationnels (Strava, Weather, Google Calendar, Outlook).

Malgré la promesse de « tableau de bord personnel centralisé », les modules sont largement **cloisonnés** et la **page d'accueil n'agrège presque rien**.

**Interconnexions existantes (4 seulement) :**
- Goals ← Sport + Finance (`GoalProgressCalculator`)
- Planning ← Échéances (`AggregatedCalendarQuery`)
- Exploration ← Sport (décodage polylignes, `RebuildUserCoverage`)
- Users → tous (cascade de suppression par events/listeners)

**Manques principaux :**
1. **Dashboard d'accueil quasi vide** — n'affiche que le compteur d'activités Sport + le nombre d'intégrations. To-Do, Objectifs, Finance, Planning, Échéances, Moto, Exploration en sont absents.
2. **Catalogue de modules & sidebar hardcodés** dans `technical/web-authentication/src/Livewire/Dashboard.php:19-31` et `resources/views/components/ui/sidebar.blade.php:2-13` — aucun registre, chaque module ajouté à la main.
3. **Planning ← To-Do manquant** : les tâches ont une `due_at` mais n'apparaissent pas dans le calendrier (alors que les échéances, oui).
4. **Goals trop étroit** : ne tire que de Sport + Finance.
5. **Pas de centre de notifications unifié** : chaque module gère ses rappels indépendamment.

---

## Décision d'architecture clé — le socle d'agrégation

Trois approches envisagées pour qu'une couche technique collecte des données de plusieurs modules **sans les importer en dur** :

| Approche | Description | Verdict |
|----------|-------------|---------|
| **A — Aggregator direct** | Un service dans `web-authentication` injecte les services de chaque module | ❌ Couple l'accueil à tous les modules (viole l'isolation OSDD) |
| **B — Contrat taggé** | Interface dans `technical/osdd`, implémentée par chaque module, collectée via `app()->tagged()` | ✅ **Retenu** |
| **C — Projection event-sourced** | Table `dashboard_widgets` mise à jour par listeners | ❌ Lourd et prématuré |

**Approche B retenue** : étend proprement le pattern `RefreshesAccessToken` déjà présent (interface implémentée cross-module) vers une **collecte déclarative**. Résout d'un coup le dashboard vide, le hardcoding du catalogue/sidebar, et l'auto-apparition des futurs modules/intégrations.

### Contrats à introduire (`technical/osdd/src/Contracts/`)
- `ProvidesDashboardSummary` → `summary(User $user): DashboardSummary` (DTO : `key`, `title`, `accent`, métrique principale, lignes secondaires, `href`, `available`).
- `ProvidesNavigationItem` → remplace les tableaux hardcodés de la sidebar et de `Dashboard::modules()`.
- `ProvidesAgendaItems` → `agendaItems(User $user, CarbonPeriod): Collection` (flux « Aujourd'hui / À venir » unifié).

Chaque module enregistre son implémentation dans son `ServiceProvider` via un tag (`dashboard.summaries`, etc.) ; les collecteurs vivent dans `web-authentication`.

---

## Phase 0 — Socle d'agrégation (prérequis de tout le reste)

**Valeur dashboard central : maximale.** Transforme l'accueil de vitrine statique en cockpit vivant.

1. Créer les contrats + DTO (`DashboardSummary`, `NavigationItem`) dans `technical/osdd`.
2. Implémenter un `XxxDashboardSummary` taggé `dashboard.summaries` dans **chacun** des 8 modules, en réutilisant les services existants : `SportStatisticsCalculator`, `CapitalCalculator`/`PerformanceCalculator`, `UpcomingExpensesQuery`, `CoverageCalculator`, comptages To-Do/Goals/Moto.
3. Réécrire `Dashboard.php` : remplacer `modules()`/`stats()` hardcodés par la collecte des tuiles taggées.
4. Réécrire `sidebar.blade.php` pour itérer sur les `ProvidesNavigationItem` collectés.

**Fichiers pivots** : `technical/web-authentication/src/Livewire/Dashboard.php:19-57`, `resources/views/components/ui/sidebar.blade.php:2-13`, nouveau `technical/osdd/src/Contracts/*`, un `*DashboardSummary.php` par module.

---

## Phase 1 — Recâblages inter-modules (faire parler les modules)

**Valeur dashboard central : élevée.**

- **Planning ← To-Do** : étendre `AggregatedCalendarQuery` (`functional/planning/src/Services/AggregatedCalendarQuery.php:16,64-82`) pour inclure les `Task` avec `due_at`, comme déjà fait pour les `RecurringExpense`.
- **Flux « Aujourd'hui / À venir » unifié** : nouveau collecteur basé sur `ProvidesAgendaItems`, agrégeant événements calendrier + tâches dues + échéances + créneaux moto favorables. Pièce maîtresse de l'accueil.
- **Goals élargi** : ajouter des cas à `GoalMetric` (`functional/goals/src/Enums/GoalMetric.php:5-66`) pour To-Do (taux de complétion), Exploration (% couverture), Moto (km/sorties). Pour chacun : case d'enum + entrée dans `GoalType::metrics()` (`Enums/GoalType.php:40-57`) + bras dans le `match` de `GoalProgressCalculator::currentValue()` (`Services/GoalProgressCalculator.php:47-58`) + injection du service source.
- **Moto ↔ Météo / Planning** : exposer les créneaux Moto-Friendly favorables (score météo existant) comme `ProvidesAgendaItems`.

---

## Phase 2 — Centre de notifications unifié

**Valeur dashboard central : moyenne-élevée.** La « cloche » du command deck.

- Aujourd'hui : chaque module notifie indépendamment (To-Do `everyFifteenMinutes`, Échéances `dailyAt('08:00')`), canaux `mail` + `database`, sans agrégation.
- Introduire une concern technique `technical/notifications` : composant Livewire « inbox/cloche » lisant la table `notifications` (canal database déjà utilisé), marquage lu/non-lu, préférences par module. Les modules continuent d'émettre — on n'ajoute qu'un **agrégateur de lecture**.

---

## Phase 3 — Nouvelles intégrations externes (architecture complète)

### Pattern commun à appliquer à chaque intégration
1. SDK framework-agnostic publié `Foutraz/SDK_X` → composer `foutraz/x`, branché en dépôt `path` (modèle `foutraz/strava`).
2. Case dans `Technical\Integrations\Enums\IntegrationProvider`.
3. Si OAuth : `XTokenRefresher implements RefreshesAccessToken` + `BuildUserXManager` action (via `ConnectionTokenResolver`).
4. Jobs de sync idempotents (`WithoutOverlapping($connectionId)`, `$tries=3`, backoff `[60,300,900]`, upsert sur clé externe unique).
5. Modèle(s) local(aux) ULID + unique `external_id`.
6. `XConnectionController` (`connect`/`callback`/`syncNow`) + entrée scheduler.
7. `XDashboardSummary` taggé (Phase 0) + sources Goals si pertinent (Phase 1).

### 3.1 — Withings (santé / métriques corporelles) — **priorité 1**
- **Pourquoi central** : poids, sommeil, fréquence cardiaque, pas → enrichit Sport + Goals + tuile santé. OAuth2 propre, API stable.
- **Cible** : nouveau module `functional/health` (ou extension `sport`). Modèles : `BodyMeasurement`, `SleepSummary`.
- **Goals** : nouveaux `GoalMetric` (poids cible, heures de sommeil).
- SDK `Foutraz/SDK_Withings` → `foutraz/withings`.

### 3.2 — GoCardless Bank Account Data (agrégation bancaire, ex-Nordigen) — **priorité 2**
- **Pourquoi central** : soldes réels + transactions → bascule Finance/Échéances du déclaratif au réel ; **détection automatique des dépenses récurrentes**. Forte valeur « vraie vue argent ». Offre gratuite UE.
- **Cible** : extension `finance` + `recurring-expenses`. Modèles : `BankAccount`, `BankTransaction`.
- Flux par requisition/redirect (proche OAuth) → resolver dédié.
- SDK `Foutraz/SDK_GoCardlessBank` → `foutraz/gocardless-bank`.

### 3.3 — Données de marché (CoinGecko + cours actions) — **priorité 3**
- **Pourquoi central** : valorise en temps réel les positions Finance (crypto + actions) → tuile Finance vivante. **Clé API seule, pas d'OAuth** → intégration la plus légère.
- **Cible** : `finance`. Pas de connexion par utilisateur (clé applicative) ; job planifié de rafraîchissement des cours mis en cache.
- SDK `Foutraz/SDK_MarketData` → `foutraz/market-data`.

### Mentions optionnelles (catalogue ouvert)
GitHub (activité dev → nouveau module), Spotify (écoutes), Liberty Rider (déjà squelette, sans API publique).

---

## Séquencement & dépendances

```
Phase 0 (socle taggé)  ──prérequis──▶  tout le reste apparaît "centralement"
        │
        ├─▶ Phase 1 (recâblages : Planning←Todo, Goals élargi, agenda unifié, Moto)
        ├─▶ Phase 2 (centre de notifications)
        └─▶ Phase 3 (intégrations : Withings → GoCardless → MarketData)
              └─ chaque intégration se branche AUTOMATIQUEMENT sur le socle Phase 0
```

Phase 0 est le verrou : sans elle, chaque nouveau module/intégration reste invisible et nécessite l'édition manuelle de l'accueil + sidebar.

---

## Vérification (par phase, à l'implémentation)

- **Phase 0** : un module fictif taggé apparaît sur l'accueil et la sidebar sans toucher `Dashboard.php`/`sidebar.blade.php` ; chaque `*DashboardSummary` testé unitairement (chiffres scopés au bon user).
- **Phase 1** : `AggregatedCalendarQuery` renvoie tâches + échéances + événements ; tests Goals par nouveau `GoalMetric` (factories) ; flux agenda trié chronologiquement.
- **Phase 2** : notification émise par un module apparaît dans l'inbox, marquage lu persiste.
- **Phase 3** : par intégration — jobs de sync mockés (`MockHandler`) prouvent l'idempotence, `RefreshesAccessToken` testé, suite verte sans credentials réels.
- **Transverse** : `vendor/bin/pint --dirty --format agent`, Larastan niveau 7, `php artisan test --compact`.

---

## Prompt Claude Code réutilisable

> Copier-coller le bloc ci-dessous (en adaptant la phase visée) pour relancer un chantier de cette roadmap dans une future session.

```
Je veux dérouler la roadmap d'interconnexions du Personal Dashboard décrite dans
ROADMAP_INTERCONNEXIONS.md (à la racine du repo). Lis-le d'abord en entier.

Chantier visé : PHASE <N> — <titre de la phase>.

Contraintes :
- Architecture OSDD : modules sous functional/*, couches techniques sous technical/*.
- Le socle d'agrégation est l'approche B : contrats taggés dans technical/osdd
  (ProvidesDashboardSummary / ProvidesNavigationItem / ProvidesAgendaItems),
  collectés via app()->tagged() dans technical/web-authentication. Respecte
  l'isolation : aucun import cross-module en dur, on passe par les contrats.
- Conventions projet (voir CLAUDE.md) : ids ULID, pas de cascade SQL (listeners),
  pas de try-catch, exceptions de domaine nommées, modèles fins, CRUD via
  lomkit/laravel-rest-api Resources, Larastan niveau 7, tests PHPUnit class-based
  avec factories et faker, docstrings en anglais (une phrase), pas de commentaires.
- Pour une nouvelle intégration (Phase 3) : suivre le pattern SDK Foutraz
  (repo Foutraz/SDK_X public, composer foutraz/x en path repo, IntegrationProvider
  enum, RefreshesAccessToken si OAuth, jobs idempotents WithoutOverlapping/tries=3,
  modèle local ULID + clé externe unique, ConnectionController, scheduler,
  *DashboardSummary taggé).
- Git : nouvelle branche feature/<id>-<slug> basée sur develop, jamais de push sur
  develop/main, commits séparés avec gitmoji et message d'une phrase, push après
  chaque commit.

Commence par brainstormer/cadrer la phase avec moi (spec) avant tout code, puis
écris un plan d'implémentation détaillé. N'implémente qu'après validation du plan.
```

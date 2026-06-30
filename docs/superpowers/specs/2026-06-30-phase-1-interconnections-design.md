# Phase 1 — Recâblages inter-modules (agenda unifié + Goals élargi)

> Design auto-validé le 2026-06-30 (mode autonome). Deuxième chantier de `ROADMAP_INTERCONNEXIONS.md`.
> Branche : `feature/phase-1-interconnections`, **empilée sur** `feature/phase-0-aggregation-socle` (le merge de #25 est bloqué par la protection de branche develop ; PRs empilées, GitHub recible sur develop au fil des merges).

## 1. Contexte

Phase 0 a posé le socle taggé (contrats dans `technical/osdd`, collecteurs dans `technical/web-authentication`, tuiles `*DashboardContribution`). Phase 1 fait **parler les modules entre eux** autour du temps et des objectifs :

1. **Agenda unifié** — un flux « Aujourd'hui / À venir » sur l'accueil agrégeant événements calendrier + tâches dues + échéances + créneaux moto favorables, via un nouveau contrat taggé.
2. **Planning ← To-Do** — la page Planning inclut désormais les tâches dues, et son agrégation passe par le contrat (ce qui supprime son import en dur de `recurring-expenses`).
3. **Goals élargi** — nouveaux `GoalMetric` alimentés par To-Do, Moto et Exploration.

## 2. Décisions clés (mode autonome)

| # | Décision | Raison |
|---|----------|--------|
| Archi agenda | **Approche 2 unifiée** | `AgendaItem` = monnaie unique ; zéro duplication ; nettoie le couplage planning→recurring existant. |
| `AgendaItem` | Surensemble de `CalendarItem` (osdd) | Permet à `AggregatedCalendarQuery` de mapper AgendaItem→CalendarItem sans changer la page Planning. |
| Source | String + accent portés par chaque module | Pas d'enum central à étendre ; modules découplés. |
| Exploration goal | **Compte de cellules**, pas % | `explorationPercentage` exige une `BoundingBox` sans région globale par user ; un compte est défini et fait une meilleure cible d'objectif. |
| Todo goal | `completionRate` sur **toutes** les tâches du user (sans fenêtre) | Un objectif « taux de complétion » se lit comme un % courant global. |
| Moto agenda | Localisation **app-config** (`moto.location`), prévision en cache, vide si non configuré | Pas de localisation persistée par user ; les créneaux météo sont propres au lieu, pas à l'utilisateur. |

## 3. Périmètre

**Dans le périmètre :**
- `technical/osdd` : contrat `ProvidesAgendaItems` + DTO `AgendaItem`.
- 4 providers d'agenda taggés `dashboard.agenda` : planning (events), recurring-expenses (échéances), todo (tâches dues), moto (créneaux favorables).
- `technical/web-authentication` : `AgendaCollector` + section « Aujourd'hui / À venir » sur l'accueil.
- `functional/planning` : `AggregatedCalendarQuery` réécrite pour consommer le tag (filtre hors moto) → supprime l'import en dur de recurring-expenses.
- `functional/goals` : 4 nouveaux `GoalMetric` + `GoalType` + bras de `currentValue()` + injection des services source.

**Hors périmètre (reporté) :**
- Centre de notifications → **Phase 2**.
- Nouvelles intégrations externes → **Phase 3**.
- Pas de cache applicatif pour l'agenda (la prévision moto est déjà cachée ; le reste = quelques requêtes scopées). YAGNI.
- La page Planning (Livewire + blade) reste sur `CalendarItem` — non modifiée structurellement.

## 4. Contrat & DTO — `technical/osdd`

`src/Contracts/ProvidesAgendaItems.php` :

```php
namespace Technical\Osdd\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Dto\AgendaItem;

interface ProvidesAgendaItems
{
    /**
     * Return this module's time-anchored agenda items within the given period for the user.
     *
     * @return Collection<int, AgendaItem>
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection;
}
```

`src/Dto/AgendaItem.php` (`final readonly`, surensemble de `CalendarItem`) :

| Champ | Type | Rôle |
|-------|------|------|
| `id` | `string` | Identifiant de l'item (clé de boucle). |
| `source` | `string` | Clé de source (`calendar`, `expense`, `task`, `moto`). |
| `title` | `string` | Libellé. |
| `startsAt` | `Carbon` | Début (clé de tri chronologique). |
| `endsAt` | `?Carbon` | Fin éventuelle. |
| `allDay` | `bool` | Toute la journée. |
| `accent` | `string` | Couleur (`cyan`/`violet`/`lime`). |
| `location` | `?string` | Lieu éventuel. |
| `link` | `?string` | Lien externe éventuel. |
| `amount` | `?string` | Montant éventuel (échéances). |
| `href` | `?string` | Cible de navigation (route module). |

Tag : `dashboard.agenda`.

## 5. Providers d'agenda (×4, taggés)

Chaque module crée `Functional\<Module>\Dashboard\<Module>AgendaProvider implements ProvidesAgendaItems`, taggé `dashboard.agenda` dans son `register()`, scopé `$user->getAuthIdentifier()`, fenêtré par `$period`.

| Module | Source des items | `source` / `accent` | Fenêtre |
|--------|------------------|---------------------|---------|
| planning | `CalendarEvent` (starts_at ∈ période) | `calendar` / cyan (Google) ou violet (Outlook) | `whereBetween('starts_at', period)` |
| recurring-expenses | `RecurringExpense` actives (next_due_at ∈ période) | `expense` / lime | via `UpcomingExpensesQuery` filtré |
| todo | `Task` non-Done avec `due_at` ∈ période | `task` / lime | `whereBetween('due_at', period)` + status ≠ Done |
| moto | `FavorableSlot` (startsAt ∈ période) à la localisation `config('moto.location')` | `moto` / accent du `RideRating` | `FavorableSlotFinder::find(forecast)`, filtré période ; **vide** si météo non configurée |

Le provider moto ignore `$user` (localisation app-globale), garde la prévision en cache (`WeatherForecastService`), et renvoie une collection vide si l'API météo n'est pas configurée (pas d'appel non caché en rendu).

## 6. Collecteur & accueil — `technical/web-authentication`

`src/Services/AgendaCollector.php` :

```php
final class AgendaCollector
{
    /**
     * Collect every tagged module agenda item over the period, sorted chronologically.
     *
     * @return Collection<int, AgendaItem>
     */
    public function for(Authenticatable $user, CarbonPeriod $period): Collection;
    // app()->tagged('dashboard.agenda') → flatMap(->agendaItems($user,$period)) → sortBy(startsAt) → values
}
```

L'accueil (`Dashboard` Livewire) passe `$agenda = app(AgendaCollector::class)->for($user, CarbonPeriod::create(now(), now()->addDays(14)))`. Une section « Aujourd'hui / À venir » est ajoutée à `dashboard.blade.php` (au-dessus de la grille modules) : items groupés « Aujourd'hui » / « À venir », triés, chacun avec heure, titre, accent, lien `href`. Composant Blade `x-ui.agenda-item` (passe frontend-design pour la présentation).

## 7. `AggregatedCalendarQuery` réécrite — `functional/planning`

Signature adaptée pour déléguer aux providers :

```php
public function forUser(Authenticatable $user, Carbon $from, Carbon $to): Collection // <int, CalendarItem>
```

Corps : `app()->tagged('dashboard.agenda')` → `flatMap(->agendaItems($user, CarbonPeriod::create($from,$to)))` → `reject(source === 'moto')` → `map(AgendaItem→CalendarItem)` → `sortBy(startsAt)->values()`. Supprime les imports de `RecurringExpenses` et `CalendarEvent` direct (l'agrégation passe par les providers). Le mapping AgendaItem→CalendarItem préserve location/link/amount.

**Caller** : le composant Livewire planning qui appelle `forUser` passe désormais `auth('web')->user()` au lieu de l'id string (changement d'une ligne). La page Planning et son blade restent sur `CalendarItem`.

## 8. Goals élargi — `functional/goals`

- `GoalType` : + `Moto`, `Exploration`, `Productivity`.
- `GoalMetric` : + `MotoDistance` (`'moto_distance'`, unité `km`, type Moto), `MotoRideCount` (`'moto_ride_count'`, `sorties`, Moto), `ExplorationCells` (`'exploration_cells'`, `cellules`, Exploration), `TodoCompletionRate` (`'todo_completion_rate'`, `%`, Productivity). Étendre `label()`, `defaultUnit()`, `type()`, `isAutomatic()` (toutes automatiques).
- `GoalType::metrics()` : mapper les nouveaux metrics à leurs types.
- `GoalProgressCalculator` : injecter `TaskCompletionCalculator` et `RidingStatsCalculator` ; ajouter les bras `match` dans `currentValue()` + loaders privés `motoRides($goal)` (par `started_at`, fenêtré starts_at/deadline comme `sportActivities`), `exploredCells($goal)` (compte ExploredCell par `first_seen_at` fenêtré), `tasks($goal)` (toutes les tâches du user, sans fenêtre → completionRate). Imports cross-module autorisés (le calculateur importe déjà sport/finance — pattern existant et assumé du module Goals « propriétaire de l'agrégation »).

La tuile `GoalsDashboardContribution` (Phase 0) bénéficie automatiquement des nouveaux metrics (elle moyenne la progression de tous les goals).

## 9. Tests (PHPUnit, factories + faker)

- **Contrat/collecteur** : un `FakeAgendaProvider` taggé apparaît dans `AgendaCollector::for()` ; tri chronologique vérifié (≥ 2 items d'ordres distincts).
- **Chaque provider** : items scopés au bon user, fenêtrés par la période (un item hors période exclu), `source`/`accent` corrects. Moto : forecast mocké (MockHandler comme les tests moto existants) → slots dans la période ; vide si non configuré.
- **`AggregatedCalendarQuery`** : renvoie events + tâches dues + échéances (3 sources), **exclut** moto, triés ; toujours `Collection<CalendarItem>`.
- **Goals** : un test par nouveau `GoalMetric` (factory) — `currentValue()` correct et scopé user.
- **Accueil** : la section agenda rend les items collectés (au moins un item visible).
- Transverse : `pint --dirty`, Larastan niveau 7, `php artisan test --compact`.

## 10. Forme d'exécution (parallélisme)

1. **Séquentiel — socle** : contrat + DTO `AgendaItem` (osdd) → `AgendaCollector` + test fake (web-auth).
2. **Fanout — 4 providers** en parallèle (fichiers disjoints, dépendent du contrat) : planning, recurring-expenses, todo, moto.
3. **Séquentiel** : `AggregatedCalendarQuery` réécrite (dépend des providers taggés) + ajustement caller Livewire.
4. **Parallèle/indépendant** : Goals élargi (enums + calculateur), indépendant de l'agenda.
5. **Séquentiel** : intégration accueil (section agenda + composant blade), puis vérif (pint/larastan/suite).

## 11. Critères d'acceptation

- Un provider d'agenda fictif taggé apparaît dans le flux de l'accueil sans toucher le câblage.
- La page Planning affiche events + tâches dues + échéances (tâches = nouveau), via le contrat, sans import en dur de recurring-expenses.
- Chaque nouveau `GoalMetric` calcule une valeur correcte et scopée user (test factory).
- `pint` propre, Larastan niveau 7 sans nouvelle erreur, suite verte sans credentials réels.

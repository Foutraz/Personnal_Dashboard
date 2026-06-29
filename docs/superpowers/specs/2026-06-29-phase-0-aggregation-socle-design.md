# Phase 0 — Socle d'agrégation (contrats taggés)

> Design validé le 2026-06-29. Premier chantier de `ROADMAP_INTERCONNEXIONS.md`.
> Branche : `feature/phase-0-aggregation-socle` (basée sur `develop`).

## 1. Contexte & problème

L'accueil (`Technical\WebAuthentication\Livewire\Dashboard`) n'agrège presque rien :
quatre `stat-tile` codées en dur (modules disponibles, intégrations, activités Sport,
dernière activité) et une grille de huit `module-card` purement descriptives, elles aussi
hardcodées. La sidebar (`resources/views/components/ui/sidebar.blade.php`) duplique le même
tableau d'items. Chaque module ajouté impose d'éditer ces deux fichiers à la main.

Phase 0 transforme l'accueil en cockpit vivant et supprime ce hardcoding, **sans coupler**
l'accueil aux modules : on introduit le socle d'agrégation **approche B** (contrats taggés),
prérequis de toutes les phases suivantes.

## 2. Périmètre

**Dans le périmètre :**
- Deux contrats + deux DTO dans `technical/osdd`.
- Deux collecteurs dans `technical/web-authentication`.
- Réécriture de `Dashboard.php`, `dashboard.blade.php`, `sidebar.blade.php`.
- Une classe de contribution par module (×8) implémentant les deux contrats, taggée.
- Tests unitaires (contributions + collecteurs) et test d'auto-découverte (module fictif).

**Hors périmètre (reporté) :**
- `ProvidesAgendaItems` et le flux « Aujourd'hui / À venir » → **Phase 1**.
- Goals élargi, Planning ← To-Do, Moto ↔ Météo → **Phase 1**.
- Toute mise en cache des résumés (YAGNI ; une poignée de requêtes scopées par chargement
  d'accueil est acceptable pour un dashboard personnel).
- Le module `functional/users` (pas de tuile dédiée).

## 3. Décision d'architecture

Approche B retenue (cf. roadmap). Une couche technique collecte des données de plusieurs
modules **sans les importer en dur** : chaque module implémente un contrat et l'enregistre
sous un tag ; `web-authentication` collecte via `app()->tagged()`. Aucun import cross-module ;
le seul couplage est vers les interfaces de `technical/osdd`.

**Raffinement par rapport au roadmap** : la signature `summary(User $user)` du roadmap est
remplacée par `dashboardSummary(Authenticatable $user)`. `technical/osdd` est la couche de
base **requise par** les modules `functional/*` ; lui faire importer `Functional\Users\Models\User`
créerait une inversion (voire un cycle) de dépendances. Le `User` du projet implémente déjà
`Illuminate\Contracts\Auth\Authenticatable` ; les implémentations scopent par
`$user->getAuthIdentifier()`.

## 4. Contrats & DTO — `technical/osdd`

### 4.1 Contrats (`technical/osdd/src/Contracts/`)

```php
namespace Technical\Osdd\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

interface ProvidesDashboardSummary
{
    public function dashboardSummary(Authenticatable $user): DashboardSummary;
}

interface ProvidesNavigationItem
{
    public function navigationItem(): NavigationItem;
}
```

`navigationItem()` ne prend pas l'utilisateur : la navigation d'un module est statique en
Phase 0 (la route existe toujours).

### 4.2 DTO (`technical/osdd/src/Dto/`)

`readonly class`, constructor property promotion, factory statique `make(...)`, `toArray()` —
convention maison (cf. `Functional\Finance\Services\Dto\CapitalBreakdown`).

**`DashboardSummary`**

| Champ            | Type                     | Rôle |
|------------------|--------------------------|------|
| `key`            | `string`                 | Identifiant module (`sport`, `finance`…), clé de boucle Blade. |
| `title`          | `string`                 | Libellé de la tuile. |
| `accent`         | `string`                 | Couleur d'accent (`cyan`/`violet`/`lime`). |
| `icon`           | `string`                 | Path SVG (attribut `d`). |
| `href`           | `string`                 | URL de la route module (via `route()`). |
| `order`          | `int`                    | Tri ascendant déterministe. |
| `available`      | `bool`                   | `false` ⇔ intégration externe requise non connectée. |
| `metricValue`    | `string`                 | Métrique principale pré-formatée (`'42'`, `'+3,2 %'`, `'—'`). |
| `metricUnit`     | `?string`                | Unité optionnelle (`'km'`, `'tâches'`). |
| `secondaryLines` | `array<int, string>`     | Lignes secondaires courtes (`['3 sorties', '2 en retard']`). |
| `callToAction`   | `?string`                | Libellé affiché à la place de la métrique si `!available` (`'Connecter'`). |

**`NavigationItem`**

| Champ    | Type     | Rôle |
|----------|----------|------|
| `label`  | `string` | Libellé sidebar. |
| `route`  | `string` | Nom de route Laravel. |
| `icon`   | `string` | Path SVG. |
| `order`  | `int`    | Tri ascendant. |

### 4.3 Tags

- `dashboard.summaries` → implémentations de `ProvidesDashboardSummary`.
- `dashboard.navigation` → implémentations de `ProvidesNavigationItem`.

## 5. Collecteurs — `technical/web-authentication`

`technical/web-authentication/src/Services/` :

```php
final class DashboardSummaryCollector
{
    /** @return Collection<int, DashboardSummary> */
    public function for(Authenticatable $user): Collection;   // tagged('dashboard.summaries') → map(->dashboardSummary($user)) → sortBy(order) → values
}

final class NavigationItemCollector
{
    /** @return Collection<int, NavigationItem> */
    public function all(): Collection;                        // tagged('dashboard.navigation') → map(->navigationItem()) → sortBy(order) → values
}
```

Aucune connaissance des modules concrets ; uniquement des contrats `technical/osdd`.

## 6. Réécriture UI — `technical/web-authentication`

- **`Dashboard.php`** : suppression de `modules()` et `stats()`. `render()` injecte
  `$summaries = app(DashboardSummaryCollector::class)->for(auth('web')->user())`.
- **`dashboard.blade.php`** : suppression de la rangée des quatre `stat-tile`. La grille
  unique itère sur `$summaries` : carte data-rich (`title`, `metricValue`+`metricUnit`,
  `secondaryLines`, `accent`, `icon`, lien `href`) ; si `!available`, affiche `callToAction`
  à la place de la métrique. Réutilise/adapte `x-ui.module-card`.
- **`sidebar.blade.php`** : chrome fixe conservé (« Vue d'ensemble » → `dashboard` en tête,
  « Intégrations » → `integrations` en pied). Entre les deux, itération sur `$moduleNavItems`
  partagé par un `View::composer('components.ui.sidebar', …)` enregistré dans
  `WebAuthenticationServiceProvider::boot()` (appelle `NavigationItemCollector::all()`).

## 7. Contributions par module (×8) — fanout parallèle

Une classe par module : `Functional\<Module>\Dashboard\<Module>DashboardContribution`
implémentant **`ProvidesDashboardSummary` + `ProvidesNavigationItem`**, enregistrée sous les
deux tags dans `register()` du `ServiceProvider` du module. Réutilise le service d'agrégation
existant, scopé par `$user->getAuthIdentifier()`. Aucun fichier partagé entre modules.

| Module             | Service réutilisé                                  | Métrique principale (exemple)        | `available=false` si | Route                 | Ordre |
|--------------------|----------------------------------------------------|--------------------------------------|----------------------|-----------------------|-------|
| sport              | `SportStatisticsCalculator` + `SportActivity`      | distance totale (km)                 | Strava non connecté  | `sport`               | 10 |
| finance            | `PerformanceCalculator` / `CapitalCalculator` + `Position` | performance globale (%)       | jamais (autonome)    | `finance`             | 20 |
| todo               | `TaskCompletionCalculator` + `Task`                | tâches ouvertes                      | jamais               | `todo`                | 30 |
| goals              | `GoalProgressCalculator` + `Goal`                  | progression moyenne (%)              | jamais               | `goals`               | 40 |
| planning           | `AggregatedCalendarQuery` + `CalendarEvent`        | événements à venir                   | aucun calendrier connecté | `planning`       | 50 |
| recurring-expenses | `UpcomingExpensesQuery` / `MonthlyExpenseSummary`  | coût mensuel (€)                     | jamais               | `recurring-expenses`  | 60 |
| moto               | `RidingStatsCalculator` + `MotoRide`               | sorties / km                         | jamais               | `moto`                | 70 |
| exploration        | `CoverageCalculator` + `ExploredCell`              | % de couverture                      | jamais               | `exploration`         | 80 |

Icônes : chaque contribution porte son path SVG (repris de l'actuel `Dashboard.php`/sidebar),
constante privée partagée entre `dashboardSummary()` et `navigationItem()`.

La détection « intégration connectée » (sport, planning) s'appuie sur
`Technical\Integrations\Models\IntegrationConnection` filtré par utilisateur et provider —
import d'une couche technique partagée, autorisé (pas un import cross-`functional`).

## 8. Stratégie de test (PHPUnit class-based, factories + faker)

- **Auto-découverte** : un `FakeDashboardContribution` taggé dans le test prouve qu'il
  apparaît dans `DashboardSummaryCollector::for()` et `NavigationItemCollector::all()` **sans
  modifier** `Dashboard.php`/`sidebar.blade.php`. C'est la garantie centrale de Phase 0.
- **Collecteurs** : tri par `order`, mapping correct, scoping de l'utilisateur transmis.
- **Une contribution par module** : chiffres scopés au bon utilisateur (deux users en
  factory, on vérifie l'isolation), `available`/`callToAction` selon présence d'intégration.
- **Feature accueil** : la page rend une tuile par contribution taggée.

## 9. Forme d'exécution (parallélisme)

1. **Séquentiel — socle partagé** (prérequis du fanout) :
   contrats + DTO (`technical/osdd`) → collecteurs (`technical/web-authentication`) →
   réécriture UI + test d'auto-découverte (module fictif vert).
2. **Fanout — 8 agents parallèles**, un par module : chacun écrit sa contribution + son test
   dans son propre package (fichiers disjoints, zéro conflit).
3. **Séquentiel — intégration** : `vendor/bin/pint --dirty --format agent`, Larastan niveau 7,
   `php artisan test --compact`, vérification visuelle de l'accueil.

Commits séparés à gitmoji (message d'une phrase, anglais), push après chaque commit. Jamais
de push sur `develop`/`main`.

## 10. Vérification d'acceptation

- Un module fictif taggé apparaît sur l'accueil **et** la sidebar sans toucher
  `Dashboard.php`/`sidebar.blade.php`.
- Chaque `*DashboardContribution` est testée unitairement (chiffres scopés au bon user).
- `pint --dirty` propre, Larastan niveau 7 sans nouvelle erreur, suite verte sans credentials
  réels.

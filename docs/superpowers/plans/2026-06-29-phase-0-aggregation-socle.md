# Phase 0 — Socle d'agrégation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformer l'accueil en cockpit vivant via des contrats taggés que chaque module implémente, sans coupler l'accueil aux modules.

**Architecture:** Approche B — `technical/osdd` définit deux contrats (`ProvidesDashboardSummary`, `ProvidesNavigationItem`) + deux DTO ; chaque module functional enregistre une classe de contribution sous deux tags ; `technical/web-authentication` collecte via `app()->tagged()` et alimente l'accueil + la sidebar. Aucun import cross-`functional`.

**Tech Stack:** PHP 8.4, Laravel framework v13, Livewire, OSDD (`Xefi\LaravelOSDD\LayerServiceProvider`), PHPUnit 12, Larastan, Pint, Tailwind/Alpine (Blade).

## Global Constraints

- Ids en ULID (`HasUlids`) — aucun id auto-incrément.
- Aucun `try`/`catch` ; exceptions de domaine nommées si nécessaire.
- Aucun commentaire inline ; uniquement des docstrings PHPDoc en anglais, une phrase.
- Modèles fins ; logique d'agrégation dans les services existants.
- Constructor property promotion ; types de retour et de paramètres explicites partout.
- Accolades obligatoires sur toute structure de contrôle.
- DTO : `final readonly class`, props promues (convention `Functional\*\Services\Dto\*`).
- Pas de factorisation superficielle ; pas d'observers (listeners si besoin).
- Larastan niveau 7 sans nouvelle erreur ; `vendor/bin/pint --dirty --format agent` avant de finaliser.
- Tests PHPUnit class-based, attribut `#[Test]`, `use RefreshDatabase`, factories existantes + faker.
- Branche `feature/phase-0-aggregation-socle` (basée sur `develop`) ; jamais de push sur `develop`/`main` ; commit gitmoji d'une phrase ; `git push` après chaque commit.
- L'utilisateur est typé `Illuminate\Contracts\Auth\Authenticatable` dans les contrats (jamais `Functional\Users\Models\User` dans `technical/osdd`).

## Dépendances entre tâches (DAG)

```
Task 1 (contrats + DTO osdd)
   ├─▶ Task 2  (collecteurs web-auth)            ─┐
   ├─▶ Tasks 3..10 (8 contributions modules)      ├─▶ Task 11 (bascule UI) ─▶ Task 12 (vérif)
   └────────────────────────────────────────────┘
```

**Fanout parallèle = Task 2 + Tasks 3..10** (toutes ne dépendent que de Task 1, fichiers disjoints). Task 11 attend Task 2 **et** les 8 modules. L'ancien `Dashboard.php` ignore les contributions jusqu'à Task 11 : la suite reste verte à chaque étape.

---

### Task 1: Contrats & DTO — `technical/osdd`

**Files:**
- Create: `technical/osdd/src/Contracts/ProvidesDashboardSummary.php`
- Create: `technical/osdd/src/Contracts/ProvidesNavigationItem.php`
- Create: `technical/osdd/src/Dto/DashboardSummary.php`
- Create: `technical/osdd/src/Dto/NavigationItem.php`

**Interfaces:**
- Produces:
  - `Technical\Osdd\Contracts\ProvidesDashboardSummary::dashboardSummary(Authenticatable $user): DashboardSummary`
  - `Technical\Osdd\Contracts\ProvidesNavigationItem::navigationItem(): NavigationItem`
  - `Technical\Osdd\Dto\DashboardSummary` (readonly props: `key, title, accent, icon, href, order, available, metricValue, metricUnit, secondaryLines, callToAction`)
  - `Technical\Osdd\Dto\NavigationItem` (readonly props: `label, route, icon, order`)

- [ ] **Step 1: Create the two DTOs**

`technical/osdd/src/Dto/DashboardSummary.php`:

```php
<?php

namespace Technical\Osdd\Dto;

final readonly class DashboardSummary
{
    /**
     * Describe a single module tile rendered on the dashboard.
     *
     * @param  array<int, string>  $secondaryLines
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $accent,
        public string $icon,
        public string $href,
        public int $order,
        public bool $available,
        public string $metricValue,
        public ?string $metricUnit = null,
        public array $secondaryLines = [],
        public ?string $callToAction = null,
    ) {}
}
```

`technical/osdd/src/Dto/NavigationItem.php`:

```php
<?php

namespace Technical\Osdd\Dto;

final readonly class NavigationItem
{
    /**
     * Describe a single sidebar navigation entry.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public int $order,
    ) {}
}
```

- [ ] **Step 2: Create the two contracts**

`technical/osdd/src/Contracts/ProvidesDashboardSummary.php`:

```php
<?php

namespace Technical\Osdd\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Dto\DashboardSummary;

interface ProvidesDashboardSummary
{
    /**
     * Build the dashboard summary tile scoped to the given user.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary;
}
```

`technical/osdd/src/Contracts/ProvidesNavigationItem.php`:

```php
<?php

namespace Technical\Osdd\Contracts;

use Technical\Osdd\Dto\NavigationItem;

interface ProvidesNavigationItem
{
    /**
     * Build the sidebar navigation entry for this module.
     */
    public function navigationItem(): NavigationItem;
}
```

- [ ] **Step 3: Verify autoload + static analysis**

Run: `composer dump-autoload && vendor/bin/phpstan analyse technical/osdd/src/Contracts technical/osdd/src/Dto`
Expected: no errors (PSR-4 resolves the new `Technical\Osdd\Contracts` and `Technical\Osdd\Dto` namespaces under `technical/osdd/src/`).

- [ ] **Step 4: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add technical/osdd/src/Contracts technical/osdd/src/Dto
git commit -m "✨ add dashboard aggregation contracts and dtos"
git push
```

---

### Task 2: Collecteurs + auto-découverte — `technical/web-authentication`

**Files:**
- Create: `technical/web-authentication/src/Services/DashboardSummaryCollector.php`
- Create: `technical/web-authentication/src/Services/NavigationItemCollector.php`
- Create: `tests/Feature/Dashboard/Fixtures/FakeDashboardContribution.php`
- Create: `tests/Feature/Dashboard/DashboardCollectorsTest.php`

**Interfaces:**
- Consumes: `ProvidesDashboardSummary`, `ProvidesNavigationItem`, `DashboardSummary`, `NavigationItem` (Task 1).
- Produces:
  - `Technical\WebAuthentication\Services\DashboardSummaryCollector::for(Authenticatable $user): Collection<int, DashboardSummary>`
  - `Technical\WebAuthentication\Services\NavigationItemCollector::all(): Collection<int, NavigationItem>`
  - Tags consumed: `dashboard.summaries`, `dashboard.navigation`.

- [ ] **Step 1: Write the failing test + fixture**

`tests/Feature/Dashboard/Fixtures/FakeDashboardContribution.php`:

```php
<?php

namespace Tests\Feature\Dashboard\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

class FakeDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    /**
     * Build a deterministic summary used to prove tag auto-discovery.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        return new DashboardSummary(
            key: 'fake',
            title: 'Fake',
            accent: 'cyan',
            icon: 'M0 0',
            href: '#',
            order: 5,
            available: true,
            metricValue: '42',
        );
    }

    /**
     * Build a deterministic navigation entry used to prove tag auto-discovery.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Fake', route: 'dashboard', icon: 'M0 0', order: 5);
    }
}
```

`tests/Feature/Dashboard/DashboardCollectorsTest.php`:

```php
<?php

namespace Tests\Feature\Dashboard;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;
use Technical\WebAuthentication\Services\DashboardSummaryCollector;
use Technical\WebAuthentication\Services\NavigationItemCollector;
use Tests\Feature\Dashboard\Fixtures\FakeDashboardContribution;
use Tests\TestCase;

class DashboardCollectorsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_discovers_a_tagged_summary_without_touching_the_dashboard(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.summaries']);
        $user = User::factory()->create();

        $summaries = $this->app->make(DashboardSummaryCollector::class)->for($user);

        $this->assertTrue($summaries->contains(fn (DashboardSummary $summary): bool => $summary->key === 'fake'));
    }

    #[Test]
    public function it_discovers_a_tagged_navigation_item(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.navigation']);

        $items = $this->app->make(NavigationItemCollector::class)->all();

        $this->assertTrue($items->contains(fn (NavigationItem $item): bool => $item->label === 'Fake'));
    }

    #[Test]
    public function it_orders_navigation_items_by_their_order(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.navigation']);

        $items = $this->app->make(NavigationItemCollector::class)->all();
        $orders = $items->map(fn (NavigationItem $item): int => $item->order)->all();
        $sorted = $orders;
        sort($sorted);

        $this->assertSame($sorted, $orders);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Dashboard/DashboardCollectorsTest.php`
Expected: FAIL with "Class ... DashboardSummaryCollector not found".

- [ ] **Step 3: Implement the collectors**

`technical/web-authentication/src/Services/DashboardSummaryCollector.php`:

```php
<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Dto\DashboardSummary;

final class DashboardSummaryCollector
{
    /**
     * Collect every tagged module summary scoped to the user, ordered ascending.
     *
     * @return Collection<int, DashboardSummary>
     */
    public function for(Authenticatable $user): Collection
    {
        /** @var iterable<int, ProvidesDashboardSummary> $providers */
        $providers = app()->tagged('dashboard.summaries');

        return collect($providers)
            ->map(fn (ProvidesDashboardSummary $provider): DashboardSummary => $provider->dashboardSummary($user))
            ->sortBy(fn (DashboardSummary $summary): int => $summary->order)
            ->values();
    }
}
```

`technical/web-authentication/src/Services/NavigationItemCollector.php`:

```php
<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\NavigationItem;

final class NavigationItemCollector
{
    /**
     * Collect every tagged module navigation entry, ordered ascending.
     *
     * @return Collection<int, NavigationItem>
     */
    public function all(): Collection
    {
        /** @var iterable<int, ProvidesNavigationItem> $providers */
        $providers = app()->tagged('dashboard.navigation');

        return collect($providers)
            ->map(fn (ProvidesNavigationItem $provider): NavigationItem => $provider->navigationItem())
            ->sortBy(fn (NavigationItem $item): int => $item->order)
            ->values();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Dashboard/DashboardCollectorsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add technical/web-authentication/src/Services tests/Feature/Dashboard/Fixtures tests/Feature/Dashboard/DashboardCollectorsTest.php
git commit -m "✨ add tagged dashboard summary and navigation collectors"
git push
```

---

## Module contribution pattern (Tasks 3–10)

Chaque module crée **une** classe `Functional\<Module>\Dashboard\<Module>DashboardContribution`
implémentant `ProvidesDashboardSummary` + `ProvidesNavigationItem`, et l'enregistre dans le
`register()` de son `ServiceProvider` :

```php
$this->app->tag(<Module>DashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

Chaque tâche est **indépendante** (fichiers disjoints) et peut être confiée à un agent parallèle.

---

### Task 3: Sport contribution

**Files:**
- Create: `functional/sport/src/Dashboard/SportDashboardContribution.php`
- Modify: `functional/sport/src/Providers/SportServiceProvider.php` (register tag)
- Test: `tests/Feature/Sport/SportDashboardContributionTest.php`

**Interfaces:**
- Consumes: `SportStatisticsCalculator::totalDistance(Collection): float` (mètres), `totalElevation(Collection): float` (mètres) ; `IntegrationProvider::Strava` ; model `SportActivity` (`user_id`, `distance`, `started_at`).
- Produces: tile `key='sport'`, `title='Sport'`, route `sport`, order 10.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Dashboard\SportDashboardContribution;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SportDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_summarises_the_user_distance_in_kilometres(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 5000.0]);
        SportActivity::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 99000.0]);

        $summary = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('sport', $summary->key);
        $this->assertSame(10, $summary->order);
        $this->assertSame('10', $summary->metricValue);
        $this->assertSame('km', $summary->metricUnit);
    }

    #[Test]
    public function it_is_unavailable_until_strava_is_connected(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter Strava', $disconnected->callToAction);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Strava]);
        $connected = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Sport/SportDashboardContributionTest.php`
Expected: FAIL with "Class ... SportDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/sport/src/Dashboard/SportDashboardContribution.php`:

```php
<?php

namespace Functional\Sport\Dashboard;

use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class SportDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M4 7h3l2-3h6l2 3h3M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7M9 13a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z';

    public function __construct(private SportStatisticsCalculator $calculator) {}

    /**
     * Summarise the user's total distance and connection state.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $activities = SportActivity::query()->where('user_id', $user->getAuthIdentifier())->get();
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('provider', IntegrationProvider::Strava)
            ->exists();

        return new DashboardSummary(
            key: 'sport',
            title: 'Sport',
            accent: 'cyan',
            icon: self::ICON,
            href: route('sport'),
            order: 10,
            available: $connected,
            metricValue: number_format($this->calculator->totalDistance($activities) / 1000, 0, ',', ' '),
            metricUnit: 'km',
            secondaryLines: [
                $activities->count().' activités',
                number_format($this->calculator->totalElevation($activities), 0, ',', ' ').' m D+',
            ],
            callToAction: $connected ? null : 'Connecter Strava',
        );
    }

    /**
     * Expose the sport navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Sport', route: 'sport', icon: self::ICON, order: 10);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/sport/src/Providers/SportServiceProvider.php`, inside `register()` after `parent::register();`, add:

```php
$this->app->tag(\Functional\Sport\Dashboard\SportDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Sport/SportDashboardContributionTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/sport/src/Dashboard functional/sport/src/Providers/SportServiceProvider.php tests/Feature/Sport/SportDashboardContributionTest.php
git commit -m "✨ expose sport dashboard summary and navigation contribution"
git push
```

---

### Task 4: Finance contribution

**Files:**
- Create: `functional/finance/src/Dashboard/FinanceDashboardContribution.php`
- Modify: `functional/finance/src/Providers/FinanceServiceProvider.php` (register tag)
- Test: `tests/Feature/Finance/FinanceDashboardContributionTest.php`

**Interfaces:**
- Consumes: `PerformanceCalculator::globalPerformance(Collection<Position>): PerformanceResult` (props `percentageGain: float`, `currentValue: float`, `netInvested: float`) ; model `Position` (`user_id`, `quantity`, `average_buy_price`, `current_price`).
- Produces: tile `key='finance'`, `title='Finance'`, route `finance`, order 20, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Dashboard\FinanceDashboardContribution;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_global_performance_percentage(): void
    {
        $user = User::factory()->create();
        Position::factory()->create([
            'user_id' => $user->id,
            'quantity' => 10,
            'average_buy_price' => 100,
            'current_price' => 110,
        ]);

        $summary = $this->app->make(FinanceDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('finance', $summary->key);
        $this->assertSame(20, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('%', $summary->metricUnit);
        $this->assertStringStartsWith('+', $summary->metricValue);
    }

    #[Test]
    public function it_reports_a_neutral_performance_for_a_user_without_positions(): void
    {
        $user = User::factory()->create();
        Position::factory()->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(FinanceDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('+0,0', $summary->metricValue);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Finance/FinanceDashboardContributionTest.php`
Expected: FAIL with "Class ... FinanceDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/finance/src/Dashboard/FinanceDashboardContribution.php`:

```php
<?php

namespace Functional\Finance\Dashboard;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\PerformanceCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class FinanceDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M3 17l5-5 4 4 8-8M21 8v5h-5';

    public function __construct(private PerformanceCalculator $calculator) {}

    /**
     * Summarise the user's global portfolio performance.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $positions = Position::query()->where('user_id', $user->getAuthIdentifier())->get();
        $performance = $this->calculator->globalPerformance($positions);
        $sign = $performance->percentageGain >= 0.0 ? '+' : '';

        return new DashboardSummary(
            key: 'finance',
            title: 'Finance',
            accent: 'lime',
            icon: self::ICON,
            href: route('finance'),
            order: 20,
            available: true,
            metricValue: $sign.number_format($performance->percentageGain, 1, ',', ' '),
            metricUnit: '%',
            secondaryLines: [
                number_format($performance->currentValue, 0, ',', ' ').' €',
                'Investi '.number_format($performance->netInvested, 0, ',', ' ').' €',
            ],
        );
    }

    /**
     * Expose the finance navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Finance', route: 'finance', icon: self::ICON, order: 20);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/finance/src/Providers/FinanceServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Finance\Dashboard\FinanceDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Finance/FinanceDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/finance/src/Dashboard functional/finance/src/Providers/FinanceServiceProvider.php tests/Feature/Finance/FinanceDashboardContributionTest.php
git commit -m "✨ expose finance dashboard summary and navigation contribution"
git push
```

---

### Task 5: To-Do contribution

**Files:**
- Create: `functional/todo/src/Dashboard/TodoDashboardContribution.php`
- Modify: `functional/todo/src/Providers/TodoServiceProvider.php` (register tag)
- Test: `tests/Feature/Todo/TodoDashboardContributionTest.php`

**Interfaces:**
- Consumes: `TaskCompletionCalculator::completionRate(Collection): float` ; model `Task` (`user_id`, `status` cast `TaskStatus`) ; `TaskStatus::Done`.
- Produces: tile `key='todo'`, `title='To-Do'`, route `todo`, order 30, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Dashboard\TodoDashboardContribution;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_the_user_open_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->completed()->create(['user_id' => $user->id]);
        Task::factory()->count(5)->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(TodoDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('todo', $summary->key);
        $this->assertSame(30, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('3', $summary->metricValue);
        $this->assertSame('ouvertes', $summary->metricUnit);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Todo/TodoDashboardContributionTest.php`
Expected: FAIL with "Class ... TodoDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/todo/src/Dashboard/TodoDashboardContribution.php`:

```php
<?php

namespace Functional\Todo\Dashboard;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Todo\Services\TaskCompletionCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class TodoDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11';

    public function __construct(private TaskCompletionCalculator $calculator) {}

    /**
     * Summarise the user's open task count and completion rate.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $tasks = Task::query()->where('user_id', $user->getAuthIdentifier())->get();
        $open = $tasks->filter(fn (Task $task): bool => $task->status !== TaskStatus::Done);

        return new DashboardSummary(
            key: 'todo',
            title: 'To-Do',
            accent: 'lime',
            icon: self::ICON,
            href: route('todo'),
            order: 30,
            available: true,
            metricValue: (string) $open->count(),
            metricUnit: 'ouvertes',
            secondaryLines: [
                number_format($this->calculator->completionRate($tasks), 0, ',', ' ').' % complété',
                $tasks->count().' tâches',
            ],
        );
    }

    /**
     * Expose the to-do navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'To-Do', route: 'todo', icon: self::ICON, order: 30);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/todo/src/Providers/TodoServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Todo\Dashboard\TodoDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Todo/TodoDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/todo/src/Dashboard functional/todo/src/Providers/TodoServiceProvider.php tests/Feature/Todo/TodoDashboardContributionTest.php
git commit -m "✨ expose todo dashboard summary and navigation contribution"
git push
```

---

### Task 6: Goals contribution

**Files:**
- Create: `functional/goals/src/Dashboard/GoalsDashboardContribution.php`
- Modify: `functional/goals/src/Providers/GoalsServiceProvider.php` (register tag)
- Test: `tests/Feature/Goals/GoalsDashboardContributionTest.php`

**Interfaces:**
- Consumes: `GoalProgressCalculator::progress(Goal): GoalProgress` (props `clampedPercentage(): float`, `onTrack: bool`) ; model `Goal` (`user_id`) ; factory state `manual()`.
- Produces: tile `key='goals'`, `title='Objectifs'`, route `goals`, order 40, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Dashboard\GoalsDashboardContribution;
use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalsDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_averages_the_user_goal_progress(): void
    {
        $user = User::factory()->create();
        Goal::factory()->manual()->count(2)->create(['user_id' => $user->id, 'target_value' => 100, 'manual_current_value' => 50]);

        $summary = $this->app->make(GoalsDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('goals', $summary->key);
        $this->assertSame(40, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('%', $summary->metricUnit);
        $this->assertSame('50', $summary->metricValue);
    }

    #[Test]
    public function it_reports_zero_for_a_user_without_goals(): void
    {
        $user = User::factory()->create();

        $summary = $this->app->make(GoalsDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('0', $summary->metricValue);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Goals/GoalsDashboardContributionTest.php`
Expected: FAIL with "Class ... GoalsDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/goals/src/Dashboard/GoalsDashboardContribution.php`:

```php
<?php

namespace Functional\Goals\Dashboard;

use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Functional\Goals\Services\GoalProgressCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class GoalsDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 0a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm0 6v0M12 3v3';

    public function __construct(private GoalProgressCalculator $calculator) {}

    /**
     * Summarise the user's average goal progress.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $goals = Goal::query()->where('user_id', $user->getAuthIdentifier())->get();
        $progresses = $goals->map(fn (Goal $goal): GoalProgress => $this->calculator->progress($goal));
        $average = $progresses->avg(fn (GoalProgress $progress): float => $progress->clampedPercentage()) ?? 0.0;
        $onTrack = $progresses->filter(fn (GoalProgress $progress): bool => $progress->onTrack)->count();

        return new DashboardSummary(
            key: 'goals',
            title: 'Objectifs',
            accent: 'cyan',
            icon: self::ICON,
            href: route('goals'),
            order: 40,
            available: true,
            metricValue: number_format($average, 0, ',', ' '),
            metricUnit: '%',
            secondaryLines: [
                $goals->count().' objectifs',
                $onTrack.' en bonne voie',
            ],
        );
    }

    /**
     * Expose the goals navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Objectifs', route: 'goals', icon: self::ICON, order: 40);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/goals/src/Providers/GoalsServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Goals\Dashboard\GoalsDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Goals/GoalsDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/goals/src/Dashboard functional/goals/src/Providers/GoalsServiceProvider.php tests/Feature/Goals/GoalsDashboardContributionTest.php
git commit -m "✨ expose goals dashboard summary and navigation contribution"
git push
```

---

### Task 7: Planning contribution

**Files:**
- Create: `functional/planning/src/Dashboard/PlanningDashboardContribution.php`
- Modify: `functional/planning/src/Providers/PlanningServiceProvider.php` (register tag)
- Test: `tests/Feature/Planning/PlanningDashboardContributionTest.php`

**Interfaces:**
- Consumes: model `CalendarEvent` (`user_id`, `starts_at` cast datetime) ; `IntegrationProvider::GoogleCalendar`, `IntegrationProvider::OutlookCalendar`.
- Produces: tile `key='planning'`, `title='Planning'`, route `planning`, order 50, `available` ⇔ calendrier connecté.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Dashboard\PlanningDashboardContribution;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class PlanningDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_upcoming_events_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->count(2)->create(['user_id' => $user->id, 'starts_at' => now()->addDays(3)]);
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => now()->subDay()]);
        CalendarEvent::factory()->create(['user_id' => User::factory()->create()->id, 'starts_at' => now()->addDay()]);

        $summary = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('planning', $summary->key);
        $this->assertSame(50, $summary->order);
        $this->assertSame('2', $summary->metricValue);
    }

    #[Test]
    public function it_is_unavailable_without_a_connected_calendar(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter un calendrier', $disconnected->callToAction);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoogleCalendar]);
        $connected = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Planning/PlanningDashboardContributionTest.php`
Expected: FAIL with "Class ... PlanningDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/planning/src/Dashboard/PlanningDashboardContribution.php`:

```php
<?php

namespace Functional\Planning\Dashboard;

use Functional\Planning\Models\CalendarEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class PlanningDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z';

    /**
     * Summarise the user's upcoming events and calendar connection state.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $upcoming = CalendarEvent::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('starts_at', '>', Carbon::now())
            ->count();
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('provider', [IntegrationProvider::GoogleCalendar, IntegrationProvider::OutlookCalendar])
            ->exists();

        return new DashboardSummary(
            key: 'planning',
            title: 'Planning',
            accent: 'violet',
            icon: self::ICON,
            href: route('planning'),
            order: 50,
            available: $connected,
            metricValue: (string) $upcoming,
            metricUnit: 'à venir',
            secondaryLines: [$upcoming.' événements planifiés'],
            callToAction: $connected ? null : 'Connecter un calendrier',
        );
    }

    /**
     * Expose the planning navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Planning', route: 'planning', icon: self::ICON, order: 50);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/planning/src/Providers/PlanningServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Planning\Dashboard\PlanningDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Planning/PlanningDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/planning/src/Dashboard functional/planning/src/Providers/PlanningServiceProvider.php tests/Feature/Planning/PlanningDashboardContributionTest.php
git commit -m "✨ expose planning dashboard summary and navigation contribution"
git push
```

---

### Task 8: Recurring-expenses contribution

**Files:**
- Create: `functional/recurring-expenses/src/Dashboard/RecurringExpensesDashboardContribution.php`
- Modify: `functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php` (register tag)
- Test: `tests/Feature/RecurringExpenses/RecurringExpensesDashboardContributionTest.php`

**Interfaces:**
- Consumes: `MonthlyExpenseSummary::build(Collection<RecurringExpense>): MonthlySummary` (props `monthlyTotal: float`, `yearlyTotal: float`) ; model `RecurringExpense` (`user_id`, `active`, `frequency` cast `ExpenseFrequency`).
- Produces: tile `key='deadlines'`, `title='Échéances'`, route `recurring-expenses`, order 60, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Dashboard\RecurringExpensesDashboardContribution;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpensesDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_the_user_monthly_cost(): void
    {
        $user = User::factory()->create();
        RecurringExpense::factory()->count(2)->create([
            'user_id' => $user->id,
            'amount' => 30,
            'frequency' => ExpenseFrequency::Monthly,
            'active' => true,
        ]);
        RecurringExpense::factory()->create(['user_id' => User::factory()->create()->id, 'amount' => 999]);

        $summary = $this->app->make(RecurringExpensesDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('deadlines', $summary->key);
        $this->assertSame(60, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('60', $summary->metricValue);
        $this->assertSame('€/mois', $summary->metricUnit);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/RecurringExpenses/RecurringExpensesDashboardContributionTest.php`
Expected: FAIL with "Class ... RecurringExpensesDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/recurring-expenses/src/Dashboard/RecurringExpensesDashboardContribution.php`:

```php
<?php

namespace Functional\RecurringExpenses\Dashboard;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\MonthlyExpenseSummary;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class RecurringExpensesDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z';

    public function __construct(private MonthlyExpenseSummary $summary) {}

    /**
     * Summarise the user's monthly recurring cost.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $expenses = RecurringExpense::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('active', true)
            ->get();
        $monthly = $this->summary->build($expenses);

        return new DashboardSummary(
            key: 'deadlines',
            title: 'Échéances',
            accent: 'cyan',
            icon: self::ICON,
            href: route('recurring-expenses'),
            order: 60,
            available: true,
            metricValue: number_format($monthly->monthlyTotal, 0, ',', ' '),
            metricUnit: '€/mois',
            secondaryLines: [
                number_format($monthly->yearlyTotal, 0, ',', ' ').' €/an',
                $expenses->count().' charges actives',
            ],
        );
    }

    /**
     * Expose the recurring-expenses navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Échéances', route: 'recurring-expenses', icon: self::ICON, order: 60);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\RecurringExpenses\Dashboard\RecurringExpensesDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/RecurringExpenses/RecurringExpensesDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/recurring-expenses/src/Dashboard functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php tests/Feature/RecurringExpenses/RecurringExpensesDashboardContributionTest.php
git commit -m "✨ expose recurring expenses dashboard summary and navigation contribution"
git push
```

---

### Task 9: Moto contribution

**Files:**
- Create: `functional/moto/src/Dashboard/MotoDashboardContribution.php`
- Modify: `functional/moto/src/Providers/MotoServiceProvider.php` (register tag)
- Test: `tests/Feature/Moto/MotoDashboardContributionTest.php`

**Interfaces:**
- Consumes: `RidingStatsCalculator::totalDistance(Collection<MotoRide>): float` (km), `rideCount(Collection): int` ; model `MotoRide` (`user_id`, `distance` cast `decimal:2`).
- Produces: tile `key='moto'`, `title='Météo & Moto'`, route `moto`, order 70, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Dashboard\MotoDashboardContribution;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_the_user_ride_distance(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 40.0]);
        MotoRide::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 500.0]);

        $summary = $this->app->make(MotoDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('moto', $summary->key);
        $this->assertSame(70, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('80', $summary->metricValue);
        $this->assertSame('km', $summary->metricUnit);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Moto/MotoDashboardContributionTest.php`
Expected: FAIL with "Class ... MotoDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/moto/src/Dashboard/MotoDashboardContribution.php`:

```php
<?php

namespace Functional\Moto\Dashboard;

use Functional\Moto\Models\MotoRide;
use Functional\Moto\Services\RidingStatsCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class MotoDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M3 15a4 4 0 0 0 4 4h9a4 4 0 0 0 0-8 6 6 0 0 0-11.7-1.8A4 4 0 0 0 3 15Z';

    public function __construct(private RidingStatsCalculator $calculator) {}

    /**
     * Summarise the user's total riding distance and ride count.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $rides = MotoRide::query()->where('user_id', $user->getAuthIdentifier())->get();

        return new DashboardSummary(
            key: 'moto',
            title: 'Météo & Moto',
            accent: 'violet',
            icon: self::ICON,
            href: route('moto'),
            order: 70,
            available: true,
            metricValue: number_format($this->calculator->totalDistance($rides), 0, ',', ' '),
            metricUnit: 'km',
            secondaryLines: [$this->calculator->rideCount($rides).' sorties'],
        );
    }

    /**
     * Expose the moto navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Météo & Moto', route: 'moto', icon: self::ICON, order: 70);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/moto/src/Providers/MotoServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Moto\Dashboard\MotoDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Moto/MotoDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/moto/src/Dashboard functional/moto/src/Providers/MotoServiceProvider.php tests/Feature/Moto/MotoDashboardContributionTest.php
git commit -m "✨ expose moto dashboard summary and navigation contribution"
git push
```

---

### Task 10: Exploration contribution

**Files:**
- Create: `functional/exploration/src/Dashboard/ExplorationDashboardContribution.php`
- Modify: `functional/exploration/src/Providers/ExplorationServiceProvider.php` (register tag)
- Test: `tests/Feature/Exploration/ExplorationDashboardContributionTest.php`

**Interfaces:**
- Consumes: model `ExploredCell` (`user_id`, one row per grid cell). Pas de service (le `CoverageCalculator` exige une `BoundingBox`, hors périmètre d'une tuile).
- Produces: tile `key='maps'`, `title='Cartes'`, route `exploration`, order 80, toujours `available=true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Dashboard\ExplorationDashboardContribution;
use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExplorationDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_the_user_explored_cells(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(4)->create(['user_id' => $user->id]);
        ExploredCell::factory()->count(7)->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(ExplorationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('maps', $summary->key);
        $this->assertSame(80, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('4', $summary->metricValue);
        $this->assertSame('cellules', $summary->metricUnit);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Exploration/ExplorationDashboardContributionTest.php`
Expected: FAIL with "Class ... ExplorationDashboardContribution not found".

- [ ] **Step 3: Implement the contribution**

`functional/exploration/src/Dashboard/ExplorationDashboardContribution.php`:

```php
<?php

namespace Functional\Exploration\Dashboard;

use Functional\Exploration\Models\ExploredCell;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class ExplorationDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M9 6 3 4v14l6 2 6-2 6 2V6l-6-2-6 2Zm0 0v14m6-12v14';

    /**
     * Summarise the user's explored grid cell count.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $cells = ExploredCell::query()->where('user_id', $user->getAuthIdentifier())->count();

        return new DashboardSummary(
            key: 'maps',
            title: 'Cartes',
            accent: 'violet',
            icon: self::ICON,
            href: route('exploration'),
            order: 80,
            available: true,
            metricValue: number_format($cells, 0, ',', ' '),
            metricUnit: 'cellules',
            secondaryLines: [$cells.' cellules explorées'],
        );
    }

    /**
     * Expose the exploration navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Cartes', route: 'exploration', icon: self::ICON, order: 80);
    }
}
```

- [ ] **Step 4: Register the tag in the provider**

In `functional/exploration/src/Providers/ExplorationServiceProvider.php`, inside `register()`, add:

```php
$this->app->tag(\Functional\Exploration\Dashboard\ExplorationDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Exploration/ExplorationDashboardContributionTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add functional/exploration/src/Dashboard functional/exploration/src/Providers/ExplorationServiceProvider.php tests/Feature/Exploration/ExplorationDashboardContributionTest.php
git commit -m "✨ expose exploration dashboard summary and navigation contribution"
git push
```

---

### Task 11: Bascule UI — Dashboard, grille, sidebar

> **Prérequis** : Tasks 2 et 3–10 terminées (les 8 contributions sont taggées).
> **REQUIRED SUB-SKILL au moment du rendu** : `frontend-design:frontend-design` pour la passe visuelle de `module-card.blade.php`.

**Files:**
- Modify: `technical/web-authentication/src/Livewire/Dashboard.php`
- Modify: `technical/web-authentication/src/Providers/WebAuthenticationServiceProvider.php` (View::composer)
- Modify: `technical/web-authentication/resources/views/livewire/dashboard.blade.php`
- Modify: `resources/views/components/ui/module-card.blade.php`
- Modify: `resources/views/components/ui/sidebar.blade.php`
- Modify (rewrite): `tests/Feature/Dashboard/DashboardStatsTest.php`

**Interfaces:**
- Consumes: `DashboardSummaryCollector::for()`, `NavigationItemCollector::all()` (Task 2) ; les 8 contributions (Tasks 3–10).

- [ ] **Step 1: Rewrite the dashboard view-data test (failing)**

Replace the entire content of `tests/Feature/Dashboard/DashboardStatsTest.php`:

```php
<?php

namespace Tests\Feature\Dashboard;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_passes_one_ordered_summary_per_module_to_the_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('summaries', function (Collection $summaries): bool {
                $orders = $summaries->map(fn (DashboardSummary $summary): int => $summary->order)->all();
                $sorted = $orders;
                sort($sorted);

                return $summaries->count() === 8 && $orders === $sorted;
            });
    }

    #[Test]
    public function it_scopes_the_sport_metric_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 50000.0]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('summaries', function (Collection $summaries): bool {
                $sport = $summaries->firstWhere(fn (DashboardSummary $summary): bool => $summary->key === 'sport');

                return $sport !== null && $sport->metricValue === '0';
            });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Dashboard/DashboardStatsTest.php`
Expected: FAIL (view has `stats`/`modules`, not `summaries`).

- [ ] **Step 3: Rewrite `Dashboard.php`**

Replace the entire content of `technical/web-authentication/src/Livewire/Dashboard.php`:

```php
<?php

namespace Technical\WebAuthentication\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\WebAuthentication\Services\DashboardSummaryCollector;

class Dashboard extends Component
{
    /**
     * Render the authenticated dashboard shell with collected module summaries.
     */
    #[Layout('layouts.app')]
    #[Title('Dashboard')]
    public function render(DashboardSummaryCollector $collector): View
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = auth('web')->user();

        return view('web-authentication::livewire.dashboard', [
            'summaries' => $collector->for($user),
        ]);
    }
}
```

- [ ] **Step 4: Add the sidebar View::composer in the provider**

In `technical/web-authentication/src/Providers/WebAuthenticationServiceProvider.php`, add the import `use Illuminate\Support\Facades\View;` and, at the end of `boot()`:

```php
View::composer('components.ui.sidebar', function (\Illuminate\View\View $view): void {
    $view->with('moduleNavItems', $this->app->make(\Technical\WebAuthentication\Services\NavigationItemCollector::class)->all());
});
```

- [ ] **Step 5: Rewrite the dashboard blade grid**

Replace the entire content of `technical/web-authentication/resources/views/livewire/dashboard.blade.php`:

```blade
<div>
    <x-slot:header>Dashboard</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Bonjour {{ auth()->user()?->name }}</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre <span class="text-cyan text-glow-cyan">command deck</span> personnel.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Tous vos modules réunis sur une seule surface, agrégés en temps réel.
            </p>
        </div>
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="font-display text-lg font-semibold tracking-tight">Modules</h3>
            <span class="text-xs text-faint">{{ $summaries->count() }} modules</span>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($summaries as $index => $summary)
                <x-ui.module-card
                    :title="$summary->title"
                    :accent="$summary->accent"
                    :available="$summary->available"
                    :href="$summary->href"
                    :icon-path="$summary->icon"
                    :metric-value="$summary->metricValue"
                    :metric-unit="$summary->metricUnit"
                    :secondary-lines="$summary->secondaryLines"
                    :call-to-action="$summary->callToAction"
                    :delay="$index * 60"
                />
            @endforeach
        </div>
    </section>
</div>
```

- [ ] **Step 6: Rewrite `module-card.blade.php` (data-rich tile)**

Replace the entire content of `resources/views/components/ui/module-card.blade.php`:

```blade
@props([
    'title' => '',
    'iconPath' => null,
    'accent' => 'cyan',
    'href' => '#',
    'available' => false,
    'delay' => 0,
    'metricValue' => '',
    'metricUnit' => null,
    'secondaryLines' => [],
    'callToAction' => null,
])

@php
    $accents = [
        'cyan' => ['text' => 'text-cyan', 'bg' => 'bg-cyan-soft', 'ring' => 'group-hover:border-cyan/40'],
        'violet' => ['text' => 'text-violet', 'bg' => 'bg-violet-soft', 'ring' => 'group-hover:border-violet/40'],
        'lime' => ['text' => 'text-lime', 'bg' => 'bg-lime-soft', 'ring' => 'group-hover:border-lime/40'],
    ];
    $a = $accents[$accent] ?? $accents['cyan'];
@endphp

<a
    href="{{ $href }}"
    x-data
    x-reveal:{{ $delay }}
    {{ $attributes->class(['group glass glass-hover relative flex flex-col overflow-hidden p-6 cursor-pointer']) }}
>
    <div class="flex items-start justify-between">
        <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $a['bg'] }} {{ $a['text'] }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
        </span>

        @if ($available)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Actif
            </span>
        @else
            <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">
                À connecter
            </span>
        @endif
    </div>

    <h3 class="mt-5 font-display text-lg font-semibold tracking-tight">{{ $title }}</h3>

    @if ($available)
        <div class="mt-3 flex items-baseline gap-1.5">
            <span class="font-display text-3xl font-bold {{ $a['text'] }}">{{ $metricValue }}</span>
            @if ($metricUnit)
                <span class="text-sm text-muted">{{ $metricUnit }}</span>
            @endif
        </div>
        <div class="mt-2 flex flex-col gap-0.5">
            @foreach ($secondaryLines as $line)
                <p class="text-xs text-muted">{{ $line }}</p>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-sm leading-relaxed text-muted">{{ $callToAction }}</p>
    @endif

    <div class="mt-5 flex items-center gap-1.5 text-sm font-medium {{ $a['text'] }}">
        <span>Ouvrir</span>
        <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
    </div>

    <div class="pointer-events-none absolute -bottom-12 -right-12 h-32 w-32 rounded-full {{ $a['bg'] }} opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"></div>
</a>
```

- [ ] **Step 7: Rewrite the sidebar nav loop**

In `resources/views/components/ui/sidebar.blade.php`, replace the `@php $navItems = [...] @endphp` block and the `<nav>` loop so the chrome is fixed and modules iterate `$moduleNavItems`. Replace the `<nav class="mt-3 ...">...</nav>` element with:

```blade
<nav class="mt-3 flex flex-1 flex-col gap-1">
    <x-ui.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
        <x-slot:icon>
            <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/></svg>
        </x-slot:icon>
        Vue d'ensemble
    </x-ui.nav-link>

    @foreach ($moduleNavItems as $item)
        <x-ui.nav-link :href="route($item->route)" :active="request()->routeIs($item->route)">
            <x-slot:icon>
                <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item->icon }}"/></svg>
            </x-slot:icon>
            {{ $item->label }}
        </x-ui.nav-link>
    @endforeach

    <x-ui.nav-link :href="route('integrations')" :active="request()->routeIs('integrations')">
        <x-slot:icon>
            <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
        </x-slot:icon>
        Intégrations
    </x-ui.nav-link>
</nav>
```

Also delete the now-unused `@php $navItems = [...] @endphp` block at the top of the file.

- [ ] **Step 8: Run the dashboard + access tests**

Run: `php artisan test --compact tests/Feature/Dashboard`
Expected: PASS — `DashboardStatsTest` (2), `DashboardCollectorsTest` (3), `DashboardAccessTest` (3, the module titles `Sport`/`Finance`/`Cartes`/`Météo & Moto`/`Échéances`/`Objectifs` now render from the tiles).

- [ ] **Step 9: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add technical/web-authentication resources/views/components/ui/module-card.blade.php resources/views/components/ui/sidebar.blade.php tests/Feature/Dashboard/DashboardStatsTest.php
git commit -m "♻️ render the dashboard and sidebar from tagged contributions"
git push
```

---

### Task 12: Intégration — qualité & suite complète

**Files:** none (verification only).

- [ ] **Step 1: Format the whole branch**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no remaining style issues.

- [ ] **Step 2: Static analysis at level 7**

Run: `vendor/bin/phpstan analyse`
Expected: no new errors versus the `develop` baseline.

- [ ] **Step 3: Full test suite**

Run: `php artisan test --compact`
Expected: green, including the 8 contribution tests, `tests/Feature/Dashboard/*`, and the pre-existing `DashboardAccessTest`.

- [ ] **Step 4: Visual smoke check**

Run `composer run dev` (or `npm run build`), log in, open `/dashboard`. Confirm: one tile per module ordered Sport→Finance→To-Do→Objectifs→Planning→Échéances→Météo & Moto→Cartes; the sidebar lists the eight modules between "Vue d'ensemble" and "Intégrations"; disconnected modules (Sport without Strava, Planning without a calendar) show the "À connecter" badge and their call-to-action.

- [ ] **Step 5: Final commit if Pint changed anything**

```bash
git add -A
git commit -m "🎨 apply final formatting pass for phase 0 socle"
git push
```

---

## Notes d'exécution

- **Fanout** : après Task 1, dispatcher Task 2 + Tasks 3–10 en agents parallèles (fichiers disjoints, aucun conflit). Chaque agent ne dépend que des contrats de Task 1.
- **Factories** : aucune factory n'a besoin d'être créée ; états utilisés — `Task::factory()->completed()`, `Goal::factory()->manual()`, `IntegrationConnection::factory()->for($user)->create(['provider' => ...])`. Tous les modèles ont `user_id` + `HasUlids`.
- **PSR-4** : les nouveaux namespaces (`Technical\Osdd\Contracts`, `Technical\Osdd\Dto`, `Technical\WebAuthentication\Services`, `Functional\<X>\Dashboard`) tombent sous des racines déjà autoloadées ; pas de modification de `composer.json`.
- **Larastan** : si `app()->tagged()` déclenche un avertissement de type, le docblock `@var iterable<int, Provides...>` déjà présent dans les collecteurs le couvre.

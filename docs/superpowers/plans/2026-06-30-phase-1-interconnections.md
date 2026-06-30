# Phase 1 — Recâblages inter-modules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire converger les modules autour du temps (agenda unifié sur l'accueil + page Planning enrichie des tâches) et des objectifs (Goals alimentés par To-Do, Moto, Exploration), via un nouveau contrat taggé.

**Architecture:** Approche B étendue — `ProvidesAgendaItems` + DTO `AgendaItem` dans `technical/osdd` ; chaque module time-anchored l'implémente et le tague `dashboard.agenda` ; `AgendaCollector` (web-authentication) agrège pour l'accueil ; `AggregatedCalendarQuery` (planning) consomme le tag au lieu d'imports en dur. Goals étendu par de nouveaux `GoalMetric`/`GoalType`.

**Tech Stack:** PHP 8.4, Laravel v13, Livewire, OSDD, PHPUnit 12, Larastan niveau 7, Pint.

## Global Constraints

- Ids ULID ; pas de `try/catch` ; pas de commentaires (docstrings PHPDoc anglais, une phrase) ; modèles fins.
- Constructor property promotion ; types explicites partout ; accolades obligatoires.
- DTO `final readonly` ; pas de factorisation superficielle ; pas d'observers.
- Utilisateur typé `Illuminate\Contracts\Auth\Authenticatable` dans les contrats (jamais `Functional\Users\Models\User` dans `technical/osdd`).
- Larastan niveau 7 sans nouvelle erreur (`vendor/bin/phpstan analyse --memory-limit=512M`) ; `vendor/bin/pint --dirty --format agent` avant de finaliser.
- Tests PHPUnit class-based, `#[Test]`, `use RefreshDatabase`, factories existantes + faker.
- Branche `feature/phase-1-interconnections` (sur Phase 0) ; commit gitmoji d'une phrase ; `git push` après chaque commit ; jamais de push develop/main.
- Source d'un `AgendaItem` ∈ `{'google','outlook','expense','task','moto'}` (aligné sur les valeurs de `CalendarItemSource`).

## Dépendances entre tâches

```
Task 1 (contrat + DTO osdd)
   ├─▶ Task 2 (AgendaCollector)            ─┐
   ├─▶ Task 3 (planning provider)           │
   ├─▶ Task 4 (recurring provider)          ├─▶ Task 7 (AggregatedCalendarQuery rewrite)
   ├─▶ Task 5 (todo provider)               │        (needs providers 3,4,5 tagged)
   └─▶ Task 6 (moto provider)              ─┘
   └─▶ Task 9 (dashboard agenda section) (needs 2 + ≥1 provider)
Task 8 (Goals élargi) — indépendant
Task 10 (vérif intégration) — après tout
```
Fanout parallèle : Tasks 2–6 + Task 8 (fichiers disjoints, ne dépendent que de Task 1 / du code existant).

---

### Task 1: Contrat `ProvidesAgendaItems` + DTO `AgendaItem` — `technical/osdd`

**Files:**
- Create: `technical/osdd/src/Dto/AgendaItem.php`
- Create: `technical/osdd/src/Contracts/ProvidesAgendaItems.php`

**Interfaces:**
- Produces: `Technical\Osdd\Dto\AgendaItem` (readonly: `id, source, title, startsAt, endsAt, allDay, accent, location, link, amount, href`) ; `Technical\Osdd\Contracts\ProvidesAgendaItems::agendaItems(Authenticatable $user, CarbonPeriod $period): Collection<int, AgendaItem>`.

- [ ] **Step 1: Create the DTO**

`technical/osdd/src/Dto/AgendaItem.php`:

```php
<?php

namespace Technical\Osdd\Dto;

use Illuminate\Support\Carbon;

final readonly class AgendaItem
{
    /**
     * Describe a single time-anchored item aggregated onto the dashboard agenda.
     */
    public function __construct(
        public string $id,
        public string $source,
        public string $title,
        public Carbon $startsAt,
        public ?Carbon $endsAt,
        public bool $allDay,
        public string $accent,
        public ?string $location = null,
        public ?string $link = null,
        public ?string $amount = null,
        public ?string $href = null,
    ) {}
}
```

- [ ] **Step 2: Create the contract**

`technical/osdd/src/Contracts/ProvidesAgendaItems.php`:

```php
<?php

namespace Technical\Osdd\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Dto\AgendaItem;

interface ProvidesAgendaItems
{
    /**
     * Return this module's agenda items within the period for the given user.
     *
     * @return Collection<int, AgendaItem>
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection;
}
```

- [ ] **Step 3: Verify + commit**

Run: `composer dump-autoload && vendor/bin/phpstan analyse technical/osdd/src/Contracts technical/osdd/src/Dto --memory-limit=512M`
Expected: no errors.

```bash
vendor/bin/pint --dirty --format agent
git add technical/osdd/src/Contracts/ProvidesAgendaItems.php technical/osdd/src/Dto/AgendaItem.php
git commit -m "✨ add agenda items contract and dto"
git push
```

---

### Task 2: `AgendaCollector` + auto-discovery test — `technical/web-authentication`

**Files:**
- Create: `technical/web-authentication/src/Services/AgendaCollector.php`
- Create: `tests/Feature/Dashboard/Fixtures/FakeAgendaProvider.php`
- Create: `tests/Feature/Dashboard/AgendaCollectorTest.php`

**Interfaces:**
- Consumes: `ProvidesAgendaItems`, `AgendaItem` (Task 1), tag `dashboard.agenda`.
- Produces: `Technical\WebAuthentication\Services\AgendaCollector::for(Authenticatable $user, CarbonPeriod $period): Collection<int, AgendaItem>`.

- [ ] **Step 1: Write the failing test + fixture**

`tests/Feature/Dashboard/Fixtures/FakeAgendaProvider.php`:

```php
<?php

namespace Tests\Feature\Dashboard\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

class FakeAgendaProvider implements ProvidesAgendaItems
{
    public function __construct(private string $id, private Carbon $startsAt) {}

    /**
     * Return one deterministic agenda item to prove tag aggregation and ordering.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return collect([new AgendaItem(
            id: $this->id,
            source: 'task',
            title: 'Fake '.$this->id,
            startsAt: $this->startsAt,
            endsAt: null,
            allDay: false,
            accent: 'lime',
        )]);
    }
}
```

`tests/Feature/Dashboard/AgendaCollectorTest.php`:

```php
<?php

namespace Tests\Feature\Dashboard;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\AgendaItem;
use Technical\WebAuthentication\Services\AgendaCollector;
use Tests\Feature\Dashboard\Fixtures\FakeAgendaProvider;
use Tests\TestCase;

class AgendaCollectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_aggregates_tagged_providers_sorted_chronologically(): void
    {
        $this->app->bind('fake.later', fn (): FakeAgendaProvider => new FakeAgendaProvider('later', Carbon::parse('2026-07-10 09:00')));
        $this->app->bind('fake.sooner', fn (): FakeAgendaProvider => new FakeAgendaProvider('sooner', Carbon::parse('2026-07-02 09:00')));
        $this->app->tag(['fake.later', 'fake.sooner'], 'dashboard.agenda');
        $user = User::factory()->create();

        $items = $this->app->make(AgendaCollector::class)->for($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $ids = $items->map(fn (AgendaItem $item): string => $item->id)->all();
        $this->assertSame(['sooner', 'later'], $ids);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Dashboard/AgendaCollectorTest.php`
Expected: FAIL with "Class ... AgendaCollector not found".

- [ ] **Step 3: Implement the collector**

`technical/web-authentication/src/Services/AgendaCollector.php`:

```php
<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class AgendaCollector
{
    /**
     * Collect every tagged module's agenda items over the period, sorted chronologically.
     *
     * @return Collection<int, AgendaItem>
     */
    public function for(Authenticatable $user, CarbonPeriod $period): Collection
    {
        /** @var iterable<int, ProvidesAgendaItems> $providers */
        $providers = app()->tagged('dashboard.agenda');

        return collect($providers)
            ->flatMap(fn (ProvidesAgendaItems $provider): Collection => $provider->agendaItems($user, $period))
            ->sortBy(fn (AgendaItem $item): int => $item->startsAt->getTimestamp())
            ->values();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Dashboard/AgendaCollectorTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit + push**

```bash
vendor/bin/pint --dirty --format agent
git add technical/web-authentication/src/Services/AgendaCollector.php tests/Feature/Dashboard/Fixtures/FakeAgendaProvider.php tests/Feature/Dashboard/AgendaCollectorTest.php
git commit -m "✨ add agenda collector aggregating tagged providers"
git push
```

---

## Agenda provider pattern (Tasks 3–6)

Each module creates `Functional\<Module>\Dashboard\<Module>AgendaProvider implements ProvidesAgendaItems`, tagged `dashboard.agenda` in its `ServiceProvider::register()`:

```php
$this->app->tag(<Module>AgendaProvider::class, ['dashboard.agenda']);
```

Each is independent (disjoint files) → parallel fanout.

---

### Task 3: Planning agenda provider

**Files:**
- Create: `functional/planning/src/Dashboard/PlanningAgendaProvider.php`
- Modify: `functional/planning/src/Providers/PlanningServiceProvider.php` (register tag)
- Test: `tests/Feature/Planning/PlanningAgendaProviderTest.php`

**Interfaces:**
- Consumes: `ProvidesAgendaItems`, `AgendaItem`, `CalendarEvent` (`user_id`, `provider` IntegrationProvider, `title`, `starts_at`, `ends_at`, `all_day`, `location`, `external_link`).
- Produces: agenda items `source` ∈ {'google','outlook'}, `accent` cyan (Google) / violet (Outlook), `href` = `route('planning')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Dashboard\PlanningAgendaProvider;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Osdd\Dto\AgendaItem;
use Tests\TestCase;

class PlanningAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_user_events_within_the_period(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->create(['user_id' => $user->id, 'provider' => IntegrationProvider::GoogleCalendar, 'starts_at' => Carbon::parse('2026-07-05 10:00')]);
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => Carbon::parse('2026-09-01 10:00')]);
        CalendarEvent::factory()->create(['user_id' => User::factory()->create()->id, 'starts_at' => Carbon::parse('2026-07-06 10:00')]);

        $items = $this->app->make(PlanningAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('google', $items->first()->source);
        $this->assertInstanceOf(AgendaItem::class, $items->first());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Planning/PlanningAgendaProviderTest.php`
Expected: FAIL with "Class ... PlanningAgendaProvider not found".

- [ ] **Step 3: Implement the provider**

`functional/planning/src/Dashboard/PlanningAgendaProvider.php`:

```php
<?php

namespace Functional\Planning\Dashboard;

use Functional\Planning\Models\CalendarEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class PlanningAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's calendar events within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return CalendarEvent::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereBetween('starts_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event): AgendaItem => new AgendaItem(
                id: $event->id,
                source: $event->provider === IntegrationProvider::GoogleCalendar ? 'google' : 'outlook',
                title: $event->title,
                startsAt: $event->starts_at,
                endsAt: $event->ends_at,
                allDay: $event->all_day,
                accent: $event->provider === IntegrationProvider::GoogleCalendar ? 'cyan' : 'violet',
                location: $event->location,
                link: $event->external_link,
                href: route('planning'),
            ));
    }
}
```

- [ ] **Step 4: Register the tag**

In `functional/planning/src/Providers/PlanningServiceProvider.php`, inside `register()`:

```php
$this->app->tag(\Functional\Planning\Dashboard\PlanningAgendaProvider::class, ['dashboard.agenda']);
```

- [ ] **Step 5: Run test to verify it passes + commit**

Run: `php artisan test --compact tests/Feature/Planning/PlanningAgendaProviderTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add functional/planning/src/Dashboard/PlanningAgendaProvider.php functional/planning/src/Providers/PlanningServiceProvider.php tests/Feature/Planning/PlanningAgendaProviderTest.php
git commit -m "✨ expose planning calendar events as agenda items"
git push
```

---

### Task 4: Recurring-expenses agenda provider

**Files:**
- Create: `functional/recurring-expenses/src/Dashboard/RecurringExpensesAgendaProvider.php`
- Modify: `functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php`
- Test: `tests/Feature/RecurringExpenses/RecurringExpensesAgendaProviderTest.php`

**Interfaces:**
- Consumes: `RecurringExpense` (`user_id`, `active`, `label`, `next_due_at`, `amount`).
- Produces: agenda items `source='expense'`, `accent='lime'`, `amount` set, `allDay=true`, `href=route('recurring-expenses')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Dashboard\RecurringExpensesAgendaProvider;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpensesAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_due_expenses_within_the_period(): void
    {
        $user = User::factory()->create();
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-05')]);
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-12-01')]);
        RecurringExpense::factory()->create(['user_id' => User::factory()->create()->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-06')]);

        $items = $this->app->make(RecurringExpensesAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('expense', $items->first()->source);
        $this->assertNotNull($items->first()->amount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/RecurringExpenses/RecurringExpensesAgendaProviderTest.php`
Expected: FAIL ("Class ... not found").

- [ ] **Step 3: Implement the provider**

`functional/recurring-expenses/src/Dashboard/RecurringExpensesAgendaProvider.php`:

```php
<?php

namespace Functional\RecurringExpenses\Dashboard;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class RecurringExpensesAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's active recurring expenses due within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return RecurringExpense::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('active', true)
            ->whereBetween('next_due_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('next_due_at')
            ->get()
            ->map(fn (RecurringExpense $expense): AgendaItem => new AgendaItem(
                id: $expense->id,
                source: 'expense',
                title: $expense->label,
                startsAt: $expense->next_due_at,
                endsAt: null,
                allDay: true,
                accent: 'lime',
                amount: (string) $expense->amount,
                href: route('recurring-expenses'),
            ));
    }
}
```

- [ ] **Step 4: Register the tag**

In `functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php`, inside `register()`:

```php
$this->app->tag(\Functional\RecurringExpenses\Dashboard\RecurringExpensesAgendaProvider::class, ['dashboard.agenda']);
```

- [ ] **Step 5: Run test + commit**

Run: `php artisan test --compact tests/Feature/RecurringExpenses/RecurringExpensesAgendaProviderTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add functional/recurring-expenses/src/Dashboard/RecurringExpensesAgendaProvider.php functional/recurring-expenses/src/Providers/RecurringExpensesServiceProvider.php tests/Feature/RecurringExpenses/RecurringExpensesAgendaProviderTest.php
git commit -m "✨ expose recurring expenses due dates as agenda items"
git push
```

---

### Task 5: Todo agenda provider

**Files:**
- Create: `functional/todo/src/Dashboard/TodoAgendaProvider.php`
- Modify: `functional/todo/src/Providers/TodoServiceProvider.php`
- Test: `tests/Feature/Todo/TodoAgendaProviderTest.php`

**Interfaces:**
- Consumes: `Task` (`user_id`, `title`, `due_at`, `status` TaskStatus), `TaskStatus::Done`.
- Produces: agenda items `source='task'`, `accent='lime'`, `href=route('todo')`, for non-Done tasks with `due_at` in period.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Dashboard\TodoAgendaProvider;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_uncompleted_due_tasks_within_the_period(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-05 12:00')]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-06 12:00')]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => null]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-12-01 12:00')]);
        Task::factory()->create(['user_id' => User::factory()->create()->id, 'due_at' => Carbon::parse('2026-07-07 12:00')]);

        $items = $this->app->make(TodoAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('task', $items->first()->source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Todo/TodoAgendaProviderTest.php`
Expected: FAIL ("Class ... not found").

- [ ] **Step 3: Implement the provider**

`functional/todo/src/Dashboard/TodoAgendaProvider.php`:

```php
<?php

namespace Functional\Todo\Dashboard;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class TodoAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's uncompleted tasks due within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return Task::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('due_at')
            ->get()
            ->map(fn (Task $task): AgendaItem => new AgendaItem(
                id: $task->id,
                source: 'task',
                title: $task->title,
                startsAt: $task->due_at,
                endsAt: null,
                allDay: false,
                accent: 'lime',
                href: route('todo'),
            ));
    }
}
```

- [ ] **Step 4: Register the tag**

In `functional/todo/src/Providers/TodoServiceProvider.php`, inside `register()`:

```php
$this->app->tag(\Functional\Todo\Dashboard\TodoAgendaProvider::class, ['dashboard.agenda']);
```

- [ ] **Step 5: Run test + commit**

Run: `php artisan test --compact tests/Feature/Todo/TodoAgendaProviderTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add functional/todo/src/Dashboard/TodoAgendaProvider.php functional/todo/src/Providers/TodoServiceProvider.php tests/Feature/Todo/TodoAgendaProviderTest.php
git commit -m "✨ expose due tasks as agenda items"
git push
```

---

### Task 6: Moto agenda provider (favorable weather slots)

**Files:**
- Create: `functional/moto/src/Dashboard/MotoAgendaProvider.php`
- Modify: `functional/moto/src/Providers/MotoServiceProvider.php`
- Test: `tests/Feature/Moto/MotoAgendaProviderTest.php`

**Interfaces:**
- Consumes: `WeatherForecastService` (`isConfigured(): bool`, `forecast(float $lat, float $lon): Forecast`), `FavorableSlotFinder` (`find(Forecast): array<FavorableSlot>`), `FavorableSlot` (`startsAt`/`endsAt` `DateTimeImmutable`, `score` int, `rating` RideRating), `RideRating::accent()`. Location from `config('moto.location.lat'|'lon'|'label')`.
- Produces: agenda items `source='moto'`, `accent`=`RideRating::accent()`, `allDay=false`, `href=route('moto')`; empty when weather not configured.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Moto;

use DateTimeImmutable;
use Foutraz\Weather\Dto\Forecast;
use Functional\Moto\Dashboard\MotoAgendaProvider;
use Functional\Moto\Enums\RideRating;
use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\WeatherForecastService;
use Functional\Moto\ValueObjects\FavorableSlot;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\CarbonPeriod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_favorable_slots_within_the_period_when_configured(): void
    {
        $this->bindWeather(true);
        $this->app->bind(FavorableSlotFinder::class, fn (): FavorableSlotFinder => new class extends FavorableSlotFinder
        {
            public function __construct() {}

            public function find(Forecast $forecast): array
            {
                return [
                    new FavorableSlot(new DateTimeImmutable('2026-07-05 14:00'), new DateTimeImmutable('2026-07-05 18:00'), 80, RideRating::Excellent),
                    new FavorableSlot(new DateTimeImmutable('2026-12-05 14:00'), new DateTimeImmutable('2026-12-05 18:00'), 75, RideRating::Good),
                ];
            }
        });

        $items = $this->app->make(MotoAgendaProvider::class)->agendaItems(User::factory()->create(), CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('moto', $items->first()->source);
        $this->assertSame('lime', $items->first()->accent);
    }

    #[Test]
    public function it_returns_nothing_when_weather_is_not_configured(): void
    {
        $this->bindWeather(false);

        $items = $this->app->make(MotoAgendaProvider::class)->agendaItems(User::factory()->create(), CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(0, $items);
    }

    private function bindWeather(bool $configured): void
    {
        $this->app->bind(WeatherForecastService::class, fn (): WeatherForecastService => new class($configured) extends WeatherForecastService
        {
            public function __construct(private bool $ready) {}

            public function isConfigured(): bool
            {
                return $this->ready;
            }

            public function forecast(float $lat, float $lon): Forecast
            {
                return new Forecast($lat, $lon, []);
            }
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Moto/MotoAgendaProviderTest.php`
Expected: FAIL ("Class ... MotoAgendaProvider not found").

- [ ] **Step 3: Implement the provider**

`functional/moto/src/Dashboard/MotoAgendaProvider.php`:

```php
<?php

namespace Functional\Moto\Dashboard;

use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\WeatherForecastService;
use Functional\Moto\ValueObjects\FavorableSlot;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class MotoAgendaProvider implements ProvidesAgendaItems
{
    public function __construct(
        private WeatherForecastService $weather,
        private FavorableSlotFinder $slotFinder,
    ) {}

    /**
     * Map favorable riding weather slots at the configured location within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        if (! $this->weather->isConfigured()) {
            return collect();
        }

        $forecast = $this->weather->forecast((float) config('moto.location.lat'), (float) config('moto.location.lon'));

        return collect($this->slotFinder->find($forecast))
            ->map(fn (FavorableSlot $slot): AgendaItem => new AgendaItem(
                id: 'moto-'.$slot->startsAt->getTimestamp(),
                source: 'moto',
                title: 'Créneau moto favorable',
                startsAt: Carbon::instance($slot->startsAt),
                endsAt: Carbon::instance($slot->endsAt),
                allDay: false,
                accent: $slot->rating->accent(),
                href: route('moto'),
            ))
            ->filter(fn (AgendaItem $item): bool => $item->startsAt->betweenIncluded($period->getStartDate(), $period->getEndDate()))
            ->values();
    }
}
```

- [ ] **Step 4: Register the tag**

In `functional/moto/src/Providers/MotoServiceProvider.php`, inside `register()`:

```php
$this->app->tag(\Functional\Moto\Dashboard\MotoAgendaProvider::class, ['dashboard.agenda']);
```

- [ ] **Step 5: Run test + commit**

Run: `php artisan test --compact tests/Feature/Moto/MotoAgendaProviderTest.php`
Expected: PASS (2 tests).

```bash
vendor/bin/pint --dirty --format agent
git add functional/moto/src/Dashboard/MotoAgendaProvider.php functional/moto/src/Providers/MotoServiceProvider.php tests/Feature/Moto/MotoAgendaProviderTest.php
git commit -m "✨ expose favorable riding weather slots as agenda items"
git push
```

---

### Task 7: Rewrite `AggregatedCalendarQuery` on the contract + add `CalendarItemSource::Task`

> **Prérequis** : Tasks 3, 4, 5 (providers calendar/expense/task taggés). Task 6 (moto) facultative mais sera filtrée.

**Files:**
- Modify: `functional/planning/src/Services/Dto/CalendarItemSource.php` (add `Task` case)
- Modify: `functional/planning/src/Services/AggregatedCalendarQuery.php` (rewrite)
- Modify: `functional/planning/src/Livewire/PlanningDashboard.php` (caller: pass Authenticatable)
- Test: `tests/Feature/Planning/AggregatedCalendarQueryTest.php` (create or extend)

**Interfaces:**
- Consumes: tag `dashboard.agenda`, `AgendaItem`. Produces: `AggregatedCalendarQuery::forUser(Authenticatable $user, Carbon $from, Carbon $to): Collection<int, CalendarItem>` (unchanged return type).

- [ ] **Step 1: Add the `Task` case to `CalendarItemSource`**

In `functional/planning/src/Services/Dto/CalendarItemSource.php`: add `case Task = 'task';` and its `label()` arm (`self::Task => 'Tâches',`) and `color()` arm (`self::Task => 'lime',`).

- [ ] **Step 2: Write the failing test**

`tests/Feature/Planning/AggregatedCalendarQueryTest.php`:

```php
<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Services\AggregatedCalendarQuery;
use Functional\Planning\Services\Dto\CalendarItem;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AggregatedCalendarQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_events_tasks_and_expenses_excluding_moto(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => Carbon::parse('2026-07-05 10:00')]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-06 10:00')]);
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-07')]);

        $items = $this->app->make(AggregatedCalendarQuery::class)
            ->forUser($user, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $sources = $items->map(fn (CalendarItem $item): string => $item->source->value)->all();
        $this->assertContains('task', $sources);
        $this->assertContains('expense', $sources);
        $this->assertNotContains('moto', $sources);
        $this->assertSame(CalendarItemSource::class, $items->first()->source::class);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Planning/AggregatedCalendarQueryTest.php`
Expected: FAIL (current `forUser(string ...)` signature / no Task source).

- [ ] **Step 4: Rewrite `AggregatedCalendarQuery`**

Replace the entire content of `functional/planning/src/Services/AggregatedCalendarQuery.php`:

```php
<?php

namespace Functional\Planning\Services;

use Functional\Planning\Services\Dto\CalendarItem;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

class AggregatedCalendarQuery
{
    /**
     * Merge the user's calendar commitments (events, tasks, expenses) within the range.
     *
     * @return Collection<int, CalendarItem>
     */
    public function forUser(Authenticatable $user, Carbon $from, Carbon $to): Collection
    {
        /** @var iterable<int, ProvidesAgendaItems> $providers */
        $providers = app()->tagged('dashboard.agenda');

        return collect($providers)
            ->flatMap(fn (ProvidesAgendaItems $provider): Collection => $provider->agendaItems($user, CarbonPeriod::create($from, $to)))
            ->reject(fn (AgendaItem $item): bool => $item->source === 'moto')
            ->map(fn (AgendaItem $item): CalendarItem => new CalendarItem(
                $item->id,
                CalendarItemSource::from($item->source),
                $item->title,
                $item->startsAt,
                $item->endsAt,
                $item->allDay,
                $item->location,
                $item->link,
                $item->amount,
            ))
            ->sortBy(fn (CalendarItem $item): int => $item->startsAt->getTimestamp())
            ->values();
    }
}
```

- [ ] **Step 5: Update the Livewire caller**

In `functional/planning/src/Livewire/PlanningDashboard.php` (around lines 165–168): replace `$userId = (string) Auth::id();` and the two `forUser($userId, ...)` calls so they pass the authenticated user:

```php
$user = Auth::user();
$items = $aggregated->forUser($user, $period['from'], $period['to']);
$upcoming = $aggregated->forUser($user, Carbon::now()->startOfDay(), Carbon::now()->addDays(14)->endOfDay());
```

Add `use Illuminate\Contracts\Auth\Authenticatable;` only if a type hint is needed; `Auth::user()` returns the contract instance already. Remove the now-unused `$userId` variable.

- [ ] **Step 6: Run tests + commit**

Run: `php artisan test --compact tests/Feature/Planning`
Expected: PASS — `AggregatedCalendarQueryTest`, `PlanningAgendaProviderTest`, and the pre-existing planning tests stay green.

```bash
vendor/bin/pint --dirty --format agent
git add functional/planning/src/Services/AggregatedCalendarQuery.php functional/planning/src/Services/Dto/CalendarItemSource.php functional/planning/src/Livewire/PlanningDashboard.php tests/Feature/Planning/AggregatedCalendarQueryTest.php
git commit -m "♻️ aggregate the planning calendar through tagged agenda providers"
git push
```

---

### Task 8: Goals élargi — To-Do, Moto, Exploration metrics

**Files:**
- Modify: `functional/goals/src/Enums/GoalType.php` (add cases + metrics())
- Modify: `functional/goals/src/Enums/GoalMetric.php` (add cases + label/defaultUnit/type)
- Modify: `functional/goals/src/Services/GoalProgressCalculator.php` (inject services + arms + loaders)
- Test: `tests/Feature/Goals/GoalMetricExtensionsTest.php`

**Interfaces:**
- Consumes: `TaskCompletionCalculator::completionRate(Collection): float`, `RidingStatsCalculator::totalDistance(Collection): float` / `rideCount(Collection): int`, models `Task`, `MotoRide` (`started_at`), `ExploredCell` (`first_seen_at`).
- Produces: `GoalMetric::{MotoDistance, MotoRideCount, ExplorationCells, TodoCompletionRate}`, `GoalType::{Moto, Exploration, Productivity}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Moto\Models\MotoRide;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalMetricExtensionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_computes_moto_distance_goal_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 40.0]);
        MotoRide::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 500.0]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::MotoDistance, 'target_value' => 200]);

        $this->assertSame(80.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }

    #[Test]
    public function it_computes_todo_completion_rate_goal(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->completed()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::TodoCompletionRate, 'target_value' => 100]);

        $this->assertSame(25.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }

    #[Test]
    public function it_computes_exploration_cells_goal(): void
    {
        $user = User::factory()->create();
        \Functional\Exploration\Models\ExploredCell::factory()->count(4)->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::ExplorationCells, 'target_value' => 10]);

        $this->assertSame(4.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Goals/GoalMetricExtensionsTest.php`
Expected: FAIL (unknown enum cases `GoalMetric::MotoDistance` etc.).

- [ ] **Step 3: Extend `GoalType`**

In `functional/goals/src/Enums/GoalType.php`: add cases `Moto = 'moto'`, `Exploration = 'exploration'`, `Productivity = 'productivity'`. Extend `metrics()` with arms:

```php
self::Moto => [GoalMetric::MotoDistance, GoalMetric::MotoRideCount],
self::Exploration => [GoalMetric::ExplorationCells],
self::Productivity => [GoalMetric::TodoCompletionRate],
```

CRITICAL — PHP `match` is exhaustive and throws `UnhandledMatchError` on a missing case. Read the WHOLE file and add an arm for each of the three new cases to **every** `match ($this)` method present (e.g. `label()`, `color()`/`accent()`, `metrics()`). Mirror the existing per-case shape. Sensible labels: Moto → 'Moto', Exploration → 'Exploration', Productivity → 'Productivité'; pick a color from the project palette (cyan/violet/lime) per case.

- [ ] **Step 4: Extend `GoalMetric`**

In `functional/goals/src/Enums/GoalMetric.php`: add cases:

```php
case MotoDistance = 'moto_distance';
case MotoRideCount = 'moto_ride_count';
case ExplorationCells = 'exploration_cells';
case TodoCompletionRate = 'todo_completion_rate';
```

Add their arms to `label()` (`'Distance moto'`, `'Sorties moto'`, `'Cellules explorées'`, `'Taux de complétion'`), `defaultUnit()` (`'km'`, `'sorties'`, `'cellules'`, `'%'`), and `type()` (`GoalType::Moto`, `GoalType::Moto`, `GoalType::Exploration`, `GoalType::Productivity`). `isAutomatic()` stays `$this !== self::Manual` (all new metrics are automatic). CRITICAL — add an arm for each of the 4 new cases to **every** `match ($this)` method in the file (missing arms throw `UnhandledMatchError`).

- [ ] **Step 5: Extend `GoalProgressCalculator`**

In `functional/goals/src/Services/GoalProgressCalculator.php`:
- Add to the constructor: `private TaskCompletionCalculator $taskCompletion`, `private RidingStatsCalculator $ridingStats` (imports `Functional\Todo\Services\TaskCompletionCalculator`, `Functional\Moto\Services\RidingStatsCalculator`).
- Add `match` arms to `currentValue()`:

```php
GoalMetric::MotoDistance => $this->ridingStats->totalDistance($this->motoRides($goal)),
GoalMetric::MotoRideCount => (float) $this->ridingStats->rideCount($this->motoRides($goal)),
GoalMetric::ExplorationCells => (float) $this->exploredCellsCount($goal),
GoalMetric::TodoCompletionRate => $this->taskCompletion->completionRate($this->tasks($goal)),
```

- Add private loaders (mirroring `sportActivities()`):

```php
/**
 * Load the user's moto rides bounded by the goal window.
 *
 * @return Collection<int, MotoRide>
 */
private function motoRides(Goal $goal): Collection
{
    return MotoRide::query()
        ->where('user_id', $goal->user_id)
        ->when($goal->starts_at, fn (Builder $query, Carbon $startsAt): Builder => $query->where('started_at', '>=', $startsAt))
        ->when($goal->deadline, fn (Builder $query, Carbon $deadline): Builder => $query->where('started_at', '<=', $deadline))
        ->get();
}

/**
 * Count the user's explored cells first seen within the goal window.
 */
private function exploredCellsCount(Goal $goal): int
{
    return ExploredCell::query()
        ->where('user_id', $goal->user_id)
        ->when($goal->starts_at, fn (Builder $query, Carbon $startsAt): Builder => $query->where('first_seen_at', '>=', $startsAt))
        ->when($goal->deadline, fn (Builder $query, Carbon $deadline): Builder => $query->where('first_seen_at', '<=', $deadline))
        ->count();
}

/**
 * Load all of the user's tasks for the completion-rate metric.
 *
 * @return Collection<int, Task>
 */
private function tasks(Goal $goal): Collection
{
    return Task::query()->where('user_id', $goal->user_id)->get();
}
```

Add imports: `Functional\Moto\Models\MotoRide`, `Functional\Exploration\Models\ExploredCell`, `Functional\Todo\Models\Task` (and ensure `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Carbon`, `Illuminate\Support\Collection` are imported — they already are for `sportActivities()`).

- [ ] **Step 6: Run test to verify it passes + commit**

Run: `php artisan test --compact tests/Feature/Goals/GoalMetricExtensionsTest.php`
Expected: PASS (3 tests).

```bash
vendor/bin/pint --dirty --format agent
git add functional/goals/src/Enums/GoalType.php functional/goals/src/Enums/GoalMetric.php functional/goals/src/Services/GoalProgressCalculator.php tests/Feature/Goals/GoalMetricExtensionsTest.php
git commit -m "✨ extend goal metrics with todo, moto and exploration sources"
git push
```

---

### Task 9: Dashboard "Aujourd'hui / À venir" agenda section — `technical/web-authentication`

> **Prérequis** : Task 2 (AgendaCollector) + au moins un provider (Tasks 3–6).
> **REQUIRED SUB-SKILL au rendu** : `frontend-design:frontend-design` pour la présentation de l'agenda.

**Files:**
- Modify: `technical/web-authentication/src/Livewire/Dashboard.php` (pass `agenda`)
- Create: `resources/views/components/ui/agenda-item.blade.php`
- Modify: `technical/web-authentication/resources/views/livewire/dashboard.blade.php` (agenda section)
- Test: `tests/Feature/Dashboard/DashboardAgendaTest.php`

**Interfaces:**
- Consumes: `AgendaCollector::for(Authenticatable, CarbonPeriod): Collection<AgendaItem>` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Dashboard;

use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardAgendaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_an_upcoming_agenda_item_on_the_dashboard(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Payer le loyer', 'due_at' => Carbon::now()->addDays(2)]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('agenda', fn ($agenda): bool => $agenda->isNotEmpty())
            ->assertSee('Payer le loyer');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Dashboard/DashboardAgendaTest.php`
Expected: FAIL (no `agenda` view data).

- [ ] **Step 3: Pass agenda from the Dashboard component**

In `technical/web-authentication/src/Livewire/Dashboard.php`, update `render()` to also inject the agenda (keep the existing `summaries`):

```php
public function render(DashboardSummaryCollector $collector, AgendaCollector $agendaCollector): View
{
    /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
    $user = auth('web')->user();

    return view('web-authentication::livewire.dashboard', [
        'summaries' => $collector->for($user),
        'agenda' => $agendaCollector->for($user, CarbonPeriod::create(Carbon::now(), Carbon::now()->addDays(14))),
    ]);
}
```

Add imports `use Technical\WebAuthentication\Services\AgendaCollector;`, `use Illuminate\Support\Carbon;`, `use Illuminate\Support\CarbonPeriod;`.

- [ ] **Step 4: Create the agenda-item Blade component**

`resources/views/components/ui/agenda-item.blade.php`:

```blade
@props(['item'])

@php
    $accents = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'];
    $accentClass = $accents[$item->accent] ?? $accents['cyan'];
@endphp

<a href="{{ $item->href ?? '#' }}" class="glass glass-hover flex items-center gap-4 p-4">
    <div class="flex flex-col items-center {{ $accentClass }}">
        <span class="font-display text-lg font-bold leading-none">{{ $item->startsAt->isoFormat('D') }}</span>
        <span class="text-[0.65rem] uppercase tracking-wider text-faint">{{ $item->startsAt->isoFormat('MMM') }}</span>
    </div>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ $item->title }}</p>
        <p class="text-xs text-faint">
            {{ $item->allDay ? 'Toute la journée' : $item->startsAt->isoFormat('HH:mm') }}
            @if ($item->amount) · {{ $item->amount }} €@endif
            @if ($item->location) · {{ $item->location }}@endif
        </p>
    </div>
</a>
```

- [ ] **Step 5: Add the agenda section to the dashboard view**

In `technical/web-authentication/resources/views/livewire/dashboard.blade.php`, insert before the `<section class="mt-10">` modules section:

```blade
@if ($agenda->isNotEmpty())
    <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="font-display text-lg font-semibold tracking-tight">Aujourd'hui / À venir</h3>
            <span class="text-xs text-faint">{{ $agenda->count() }} éléments</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($agenda as $item)
                <x-ui.agenda-item :item="$item" />
            @endforeach
        </div>
    </section>
@endif
```

- [ ] **Step 6: Run tests + commit**

Run: `php artisan test --compact tests/Feature/Dashboard`
Expected: PASS — `DashboardAgendaTest` plus the existing dashboard tests stay green.

```bash
vendor/bin/pint --dirty --format agent
git add technical/web-authentication/src/Livewire/Dashboard.php technical/web-authentication/resources/views/livewire/dashboard.blade.php resources/views/components/ui/agenda-item.blade.php tests/Feature/Dashboard/DashboardAgendaTest.php
git commit -m "✨ add the unified today and upcoming agenda to the dashboard"
git push
```

---

### Task 10: Intégration — qualité & suite complète

**Files:** none (verification only).

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent` → no remaining issues.
- [ ] **Step 2:** `vendor/bin/phpstan analyse --memory-limit=512M` → no new errors vs baseline.
- [ ] **Step 3:** `php artisan test --compact` → 0 failures (the 4 agenda providers, AgendaCollector, AggregatedCalendarQuery, Goals extensions, dashboard agenda, and all pre-existing tests).
- [ ] **Step 4:** Visual smoke (proxy via feature tests): confirm the dashboard renders the agenda section and the Planning page now lists task items. `DashboardAgendaTest` + `AggregatedCalendarQueryTest` cover this.
- [ ] **Step 5:** Final formatting commit if Pint changed anything:

```bash
git add -A && git commit -m "🎨 apply final formatting pass for phase 1" && git push
```

---

## Notes d'exécution

- **Fanout** : après Task 1, dispatcher Tasks 2–6 + Task 8 en parallèle (fichiers disjoints). Task 7 après 3–6 ; Task 9 après 2. Task 10 en dernier.
- **Factories** : aucune nouvelle factory requise ; états utilisés — `Task::factory()->completed()`, `CalendarEvent::factory()`, `RecurringExpense::factory()`, `MotoRide::factory()`, `ExploredCell::factory()`, `Goal::factory()` (override `metric`+`target_value`).
- **Moto agenda test** : mocke `WeatherForecastService` + `FavorableSlotFinder` par classes anonymes (pas d'appel réseau), suivant le pattern des tests moto existants.
- **PSR-4** : nouveaux namespaces `Functional\<X>\Dashboard` sous racines déjà autoloadées ; pas de `composer.json` à modifier.

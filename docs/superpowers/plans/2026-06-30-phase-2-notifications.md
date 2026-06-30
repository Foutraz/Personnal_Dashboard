# Phase 2 — Centre de notifications unifié Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une cloche/inbox de notifications au header, agrégeant en lecture la table framework `notifications`, sans toucher l'émission.

**Architecture:** Nouvelle couche technique `technical/notifications` (package path-repo) avec un composant Livewire `NotificationCenter` lisant `auth('web')->user()` notifications, marquage lu/tout-lu, intégré au header. Rendu générique depuis le `data` de chaque notification, aucun couplage cross-module.

**Tech Stack:** PHP 8.4, Laravel v13, Livewire, OSDD layer (`Technical\Osdd\Providers\OsddServiceProvider`), PHPUnit 12, Larastan, Pint.

## Global Constraints

- Ids ULID pour NOS tables (la table framework `notifications` reste uuid, framework-owned — on n'en crée pas).
- Pas de `try/catch` ; pas de commentaires (docstrings PHPDoc anglais, une phrase) ; modèles/composants fins.
- Constructor property promotion ; types explicites ; accolades obligatoires.
- Larastan niveau 7 sans nouvelle erreur (`vendor/bin/phpstan analyse --memory-limit=512M`) ; `vendor/bin/pint --dirty --format agent` avant de finaliser.
- Tests PHPUnit class-based, `#[Test]`, `use RefreshDatabase`, factories.
- Branche `feature/phase-2-notifications` (sur Phase 1) ; commit gitmoji d'une phrase ; `git push` après chaque commit ; jamais de push develop/main.
- On NE modifie PAS les `Notification`/commandes/scheduler existants ; pas de préférences ; agrégateur de LECTURE uniquement.

---

### Task 1: Couche `technical/notifications` + `NotificationCenter`

**Files:**
- Create: `technical/notifications/composer.json`
- Create: `technical/notifications/src/Providers/NotificationsServiceProvider.php`
- Create: `technical/notifications/src/Livewire/NotificationCenter.php`
- Create: `technical/notifications/resources/views/livewire/notification-center.blade.php`
- Modify: `composer.json` (root — add `"technical/notifications": "*"` to `require`)
- Test: `tests/Feature/Notifications/NotificationCenterTest.php`

**Interfaces:**
- Produces: Livewire alias `notification-center` → `Technical\Notifications\Livewire\NotificationCenter` with `unreadCount` (computed/property), `recent` list, `markAsRead(string $id): void`, `markAllAsRead(): void`.

- [ ] **Step 1: Scaffold the package + register it**

`technical/notifications/composer.json` (mirror `technical/osdd/composer.json`):

```json
{
    "name": "technical/notifications",
    "description": "Unified notification center technical layer",
    "type": "layer",
    "version": "1.0.0",
    "require": {
        "xefi/laravel-osdd": "*"
    },
    "autoload": {
        "psr-4": {
            "Technical\\Notifications\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Technical\\Notifications\\Providers\\NotificationsServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

`technical/notifications/src/Providers/NotificationsServiceProvider.php`:

```php
<?php

namespace Technical\Notifications\Providers;

use Livewire\Livewire;
use Technical\Notifications\Livewire\NotificationCenter;
use Technical\Osdd\Providers\OsddServiceProvider;

class NotificationsServiceProvider extends OsddServiceProvider
{
    /**
     * Bootstrap the notification center views and Livewire component.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'notifications');

        Livewire::component('notification-center', NotificationCenter::class);
    }
}
```

Add `"technical/notifications": "*",` to the `require` block of the ROOT `composer.json` (next to the other `technical/*` entries), then register the package:

Run: `composer update technical/notifications --no-interaction`
Expected: package symlinked into `vendor/technical/notifications`, provider discovered (no errors). If that command does not pick it up, run `composer require technical/notifications:"*" --no-interaction`.

- [ ] **Step 2: Write the failing test**

`tests/Feature/Notifications/NotificationCenterTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Notifications\Livewire\NotificationCenter;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user, string $title, ?string $readAt = null): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'Functional\\Todo\\Notifications\\TaskReminderNotification',
            'data' => ['title' => $title],
            'read_at' => $readAt,
        ]);

        return $id;
    }

    #[Test]
    public function it_counts_only_the_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Loyer dû');
        $this->seedNotification($user, 'Déjà lue', now()->toDateTimeString());
        $this->seedNotification(User::factory()->create(), 'Autre user');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Loyer dû');
    }

    #[Test]
    public function it_marks_a_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->seedNotification($user, 'À lire');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAsRead', $id)
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    #[Test]
    public function it_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Une');
        $this->seedNotification($user, 'Deux');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationCenterTest.php`
Expected: FAIL — component class not found / not registered (proves the package wiring is exercised).

- [ ] **Step 4: Implement the Livewire component**

`technical/notifications/src/Livewire/NotificationCenter.php`:

```php
<?php

namespace Technical\Notifications\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class NotificationCenter extends Component
{
    /**
     * Mark a single notification of the authenticated user as read.
     */
    public function markAsRead(string $id): void
    {
        $this->userNotifications()->where('id', $id)->first()?->markAsRead();
    }

    /**
     * Mark every unread notification of the authenticated user as read.
     */
    public function markAllAsRead(): void
    {
        $this->userNotifications()->whereNull('read_at')->update(['read_at' => Carbon::now()]);
    }

    /**
     * Expose the authenticated user's unread notification count.
     */
    public function getUnreadCountProperty(): int
    {
        return $this->userNotifications()->whereNull('read_at')->count();
    }

    /**
     * Expose the authenticated user's most recent notifications.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function getRecentProperty(): Collection
    {
        return $this->userNotifications()->latest()->limit(10)->get();
    }

    /**
     * Build the base query for the authenticated user's notifications.
     *
     * @return Builder<DatabaseNotification>
     */
    private function userNotifications(): Builder
    {
        return DatabaseNotification::query()->where('notifiable_id', auth('web')->id());
    }

    /**
     * Render the notification bell and dropdown panel.
     */
    public function render(): View
    {
        return view('notifications::livewire.notification-center');
    }
}
```

Note: this queries the framework `Illuminate\Notifications\DatabaseNotification` model directly, scoped by `notifiable_id = auth('web')->id()` (the user's ULID — globally unique, single notifiable type in this app). This avoids importing `Functional\Users\Models\User` into the technical layer and avoids calling `Notifiable`-trait methods on the `Authenticatable` contract (which Larastan level 7 would reject). `unreadCount` and `recent` are Livewire computed properties (`getXProperty`), accessible as `$this->unreadCount` / `$this->recent` in the view and assertable via `assertSet('unreadCount', ...)`.

- [ ] **Step 5: Implement the view**

`technical/notifications/resources/views/livewire/notification-center.blade.php`:

```blade
<div x-data="{ open: false }" class="relative">
    <button
        @click="open = ! open"
        class="relative grid h-10 w-10 place-items-center rounded-full border border-hairline text-muted transition hover:border-cyan/40 hover:text-ink"
        aria-label="Notifications"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-cyan px-1 text-[0.65rem] font-bold text-black">{{ $this->unreadCount }}</span>
        @endif
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition.origin.top.right
        class="glass absolute right-0 z-30 mt-2 w-80 overflow-hidden p-2"
        style="display: none;"
    >
        <div class="flex items-center justify-between px-3 py-2">
            <p class="text-sm font-semibold">Notifications</p>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-xs text-cyan transition hover:text-ink">Tout marquer comme lu</button>
            @endif
        </div>
        <div class="neon-divider my-1"></div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($this->recent as $notification)
                <button
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-cyan-soft {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        @unless ($notification->read_at)<span class="h-1.5 w-1.5 rounded-full bg-cyan"></span>@endunless
                        {{ $notification->data['title'] ?? $notification->data['label'] ?? class_basename($notification->type) }}
                    </span>
                    <span class="text-xs text-faint">
                        @if (isset($notification->data['amount'])){{ $notification->data['amount'] }} {{ $notification->data['currency'] ?? '' }} · @endif
                        {{ $notification->created_at?->diffForHumans() }}
                    </span>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-sm text-faint">Aucune notification.</p>
            @endforelse
        </div>
    </div>
</div>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationCenterTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Pint + Larastan + commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse technical/notifications/src --memory-limit=512M
git add technical/notifications composer.json composer.lock tests/Feature/Notifications/NotificationCenterTest.php
git commit -m "✨ add notification center technical layer with bell inbox"
git push
```

---

### Task 2: Intégrer la cloche dans le header

> **REQUIRED SUB-SKILL au rendu** : `frontend-design:frontend-design` pour l'insertion visuelle de la cloche.

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (header right region)
- Test: `tests/Feature/Notifications/NotificationBellRendersTest.php`

**Interfaces:**
- Consumes: Livewire `notification-center` (Task 1).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Notifications/NotificationBellRendersTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBellRendersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_notification_bell_on_the_authenticated_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSeeLivewire('notification-center');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationBellRendersTest.php`
Expected: FAIL (bell not in the layout yet).

- [ ] **Step 3: Insert the bell in the header**

In `resources/views/layouts/app.blade.php`, inside the right-side container `<div class="flex items-center gap-3" x-data="{ menu: false }">`, immediately BEFORE the `<span ...>Online</span>` badge, add:

```blade
<livewire:notification-center />
```

(Keep the Online badge and user dropdown intact.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationBellRendersTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/layouts/app.blade.php tests/Feature/Notifications/NotificationBellRendersTest.php
git commit -m "✨ surface the notification bell in the app header"
git push
```

---

### Task 3: Intégration — qualité & suite complète

**Files:** none (verification only).

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent` → no remaining issues.
- [ ] **Step 2:** `vendor/bin/phpstan analyse --memory-limit=512M` → no new errors vs baseline.
- [ ] **Step 3:** `php artisan test --compact` → 0 failures (NotificationCenterTest, NotificationBellRendersTest, and all pre-existing tests including Phase 0/1).
- [ ] **Step 4:** Final formatting commit if Pint changed anything:

```bash
git add -A && git commit -m "🎨 apply final formatting pass for phase 2" && git push
```

---

## Notes d'exécution

- **New package wiring is the main risk**: after creating `technical/notifications/composer.json` and adding the root require entry, `composer update technical/notifications --no-interaction` must symlink it into `vendor/` and run package discovery so the provider (and the `notification-center` Livewire alias) load. The Task 1 test fails until this is done — that failure IS the wiring check.
- **Seeding notifications in tests**: insert directly via `$user->notifications()->create([...])` with an explicit uuid `id`, a `type` string, and a `data` array — no dependency on any functional module's Notification class (keeps the technical layer's tests self-contained).
- **No emission changes**: do not touch `TaskReminderNotification`, `ExpenseDueReminderNotification`, their commands, or the scheduler.
- **No `Functional\Users\Models\User` import in the technical layer**: the component queries `DatabaseNotification` by `auth('web')->id()` rather than `auth('web')->user()->notifications()`, sidestepping both the layer inversion and the Larastan "undefined method on Authenticatable" error. The TEST may import `User` freely (tests already do) and seed via `$user->notifications()->create([...])`.

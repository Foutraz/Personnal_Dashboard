# Gamification G2 — Streaks (régularité) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter les streaks par domaine (jours consécutifs d'activité), avec bonus XP de palier (7/30/100 jours), affichés sur le hub Joueur et la tuile dashboard.

**Architecture:** Un modèle `Streak` = projection recalculée par (user, domain), dérivée du ledger `xp_entries` (un jour actif = au moins une entrée XP non-milestone ce jour dans le domaine). Une action `UpdateStreaks` recalcule les projections et synchronise les entrées XP `streak_milestone` de façon convergente (upsert + delete des paliers non atteints), puis rafraîchit le profil — elle est appelée par `RunUserGamification` après `AwardXp`, donc chaque sync/recalcul quotidien répare automatiquement les streaks (fenêtre glissante). API REST lecture seule, purge sur `UserDeleting`, UI Livewire.

**Tech Stack:** Laravel 13 / PHP 8.4, module layer OSDD `functional/gamification`, Livewire, Tailwind, PHPUnit 12, Pint, Larastan.

## Global Constraints

- Branche `feature/gamification-phase-2` dans un worktree neuf `.claude/worktrees/gamification-phase-2` ; PR vers `develop` ; jamais de commit/push sur `main`.
- Commits : message d'une phrase max, en anglais, préfixé gitmoji ; un commit par changement logique ; push après chaque commit.
- Pas de commentaires dans le code, docstrings d'une phrase en anglais uniquement ; pas de try-catch ; ids en ULID ; règles de validation en tableau.
- `vendor/bin/pint --dirty --format agent` avant toute finalisation ; Larastan sans erreur (`vendor/bin/phpstan analyse --memory-limit=1G`).
- Tests : PHPUnit uniquement (classes, attribut `#[Test]`, snake_case `it_...`), factories pour les modèles, helper projet `faker()` (pas `$this->faker`), `php artisan test --compact --filter=...`.
- Worktree : symlinks SDK + `.env` + `public/build` copiés depuis le repo principal avant que la suite passe (voir Task 1).
- Le calcul des jours actifs DOIT exclure `rule_key = 'streak_milestone'` (sinon les bonus s'auto-entretiennent).

---

### Task 1: Créer le worktree et préparer l'environnement

**Files:**
- Modify: aucun fichier source — opérations git et environnement.

**Interfaces:**
- Consumes: `develop` à jour (contient le squash-merge G1 `f2b5d17`).
- Produces: worktree `.claude/worktrees/gamification-phase-2` sur la branche `feature/gamification-phase-2`, environnement de test fonctionnel pour les Tasks 2-8.

- [ ] **Step 1: Créer la branche et le worktree**

```bash
cd /home/quentin/LaravelProjects/Personnal_Dashboard
git fetch origin
git worktree add .claude/worktrees/gamification-phase-2 -b feature/gamification-phase-2 origin/develop
```

Expected: `Preparing worktree (new branch 'feature/gamification-phase-2')`.

- [ ] **Step 2: Préparer l'environnement de test du worktree**

```bash
for d in SDK_Strava SDK_Weather SDK_GoogleCalendar SDK_Outlook; do ln -sfn /home/quentin/LaravelProjects/$d /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/$d; done
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-2
composer install --no-interaction
cp /home/quentin/LaravelProjects/Personnal_Dashboard/.env .env
cp -r /home/quentin/LaravelProjects/Personnal_Dashboard/public/build public/build
```

Expected: `composer install` sans erreur ; `.env` et `public/build/manifest.json` présents.

- [ ] **Step 3: Vérifier que la suite existante passe dans le worktree**

```bash
php artisan test --compact tests/Feature/Gamification
```

Expected: tous les tests verts (baseline).

### Task 2: Modèle Streak, migration, factory, purge utilisateur

**Files:**
- Create: `functional/gamification/database/migrations/2026_07_03_000003_create_streaks_table.php`
- Create: `functional/gamification/src/Models/Streak.php`
- Create: `functional/gamification/database/Factories/StreakFactory.php`
- Modify: `functional/gamification/src/Listeners/DeleteUserGamificationData.php`
- Test: `tests/Feature/Gamification/StreakModelTest.php`

**Interfaces:**
- Consumes: `GamificationDomain` (enum backed string), `User`, patterns de `XpEntry`/`XpEntryFactory`.
- Produces: `Streak` (propriétés `id`, `user_id`, `domain: GamificationDomain`, `current_count: int`, `best_count: int`, `last_activity_date: ?Carbon`), constante `Streak::MILESTONE_RULE_KEY = 'streak_milestone'`, `StreakFactory`, table `streaks` unique par (user_id, domain).

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StreakModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_streak_through_its_factory(): void
    {
        $streak = Streak::factory()->create();

        $this->assertTrue(Streak::query()->whereKey($streak->id)->exists());
        $this->assertIsInt($streak->current_count);
        $this->assertIsInt($streak->best_count);
    }

    #[Test]
    public function it_ignores_a_duplicate_streak_for_the_same_domain(): void
    {
        $user = User::factory()->create();
        $streak = Streak::factory()->create(['user_id' => $user->id]);

        DB::table('streaks')->insertOrIgnore([
            'id' => strtolower((string) str()->ulid()),
            'user_id' => $user->id,
            'domain' => $streak->domain->value,
            'current_count' => 1,
            'best_count' => 1,
            'last_activity_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_deletes_the_streaks_when_the_user_is_deleted(): void
    {
        $streak = Streak::factory()->create();

        $streak->user->delete();

        $this->assertSame(0, Streak::query()->count());
    }
}
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=StreakModelTest`
Expected: FAIL — `Class "Functional\Gamification\Models\Streak" not found`.

- [ ] **Step 3: Créer la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration creating the streaks projection table.
     */
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('domain', 32);
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('best_count')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'domain']);
        });
    }

    /**
     * Reverse the migration dropping the streaks table.
     */
    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
```

- [ ] **Step 4: Créer le modèle**

```php
<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\StreakFactory;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static StreakFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property GamificationDomain $domain
 * @property int $current_count
 * @property int $best_count
 * @property Carbon|null $last_activity_date
 */
#[UseFactory(StreakFactory::class)]
class Streak extends Model
{
    /** @use HasFactory<StreakFactory> */
    use HasControl, HasFactory, HasUlids;

    public const MILESTONE_RULE_KEY = 'streak_milestone';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'domain',
        'current_count',
        'best_count',
        'last_activity_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain' => GamificationDomain::class,
            'current_count' => 'integer',
            'best_count' => 'integer',
            'last_activity_date' => 'date',
        ];
    }

    /**
     * Get the user owning the streak.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Créer la factory**

```php
<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Streak>
 */
class StreakFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Streak>
     */
    protected $model = Streak::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $current = faker()->number(1, 30);

        return [
            'user_id' => User::factory(),
            'domain' => faker()->randomElement(GamificationDomain::cases()),
            'current_count' => $current,
            'best_count' => $current + faker()->number(0, 40),
            'last_activity_date' => now()->subDays(faker()->number(0, 5))->toDateString(),
        ];
    }
}
```

- [ ] **Step 6: Étendre la purge utilisateur**

Dans `DeleteUserGamificationData::handle`, ajouter la ligne streaks (et l'import `use Functional\Gamification\Models\Streak;`) :

```php
public function handle(UserDeleting $event): void
{
    XpEntry::query()->where('user_id', $event->user->id)->delete();
    Streak::query()->where('user_id', $event->user->id)->delete();
    PlayerProfile::query()->where('user_id', $event->user->id)->delete();
}
```

Note : le test `it_deletes_the_streaks_when_the_user_is_deleted` passe par l'événement `UserDeleting` déclenché à la suppression du user (pattern des autres modules).

- [ ] **Step 7: Vérifier que les tests passent**

Run: `php artisan test --compact --filter=StreakModelTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit et push**

```bash
git add functional/gamification tests/Feature/Gamification/StreakModelTest.php
git commit -m "✨ add the streak projection model with factory and user purge"
git push -u origin feature/gamification-phase-2
```

### Task 3: Action UpdateStreaks — calcul des séries et projections

**Files:**
- Create: `functional/gamification/src/Actions/UpdateStreaks.php`
- Test: `tests/Feature/Gamification/UpdateStreaksTest.php`

**Interfaces:**
- Consumes: `XpEntry`, `Streak` (Task 2), `RefreshPlayerProfile` (existant, `handle(User $user): LevelTransition`), `GamificationDomain`.
- Produces: `UpdateStreaks::handle(User $user): LevelTransition` — recalcule toutes les projections `Streak` du user depuis le ledger puis rafraîchit le profil. Les milestones arrivent en Task 4 dans la même classe.

- [ ] **Step 1: Écrire les tests qui échouent**

```php
<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\UpdateStreaks;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateStreaksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_a_streak_from_consecutive_active_days(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 2);
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(GamificationDomain::Sport, $streak->domain);
        $this->assertSame(3, $streak->current_count);
        $this->assertSame(3, $streak->best_count);
        $this->assertSame(now()->toDateString(), $streak->last_activity_date->toDateString());
    }

    #[Test]
    public function it_counts_a_single_day_per_domain_regardless_of_entries(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Todo, 0);
        $this->entryOn($user, GamificationDomain::Todo, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_keeps_a_streak_alive_when_the_last_activity_was_yesterday(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 2);
        $this->entryOn($user, GamificationDomain::Sport, 1);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    #[Test]
    public function it_resets_the_current_count_when_the_streak_is_broken(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 10);
        $this->entryOn($user, GamificationDomain::Sport, 9);
        $this->entryOn($user, GamificationDomain::Sport, 8);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $streak = Streak::query()->where('user_id', $user->id)->sole();
        $this->assertSame(0, $streak->current_count);
        $this->assertSame(3, $streak->best_count);
    }

    #[Test]
    public function it_tracks_each_domain_independently(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);
        $this->entryOn($user, GamificationDomain::Todo, 0);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->count());
        $sport = Streak::query()->where('user_id', $user->id)->where('domain', GamificationDomain::Sport->value)->sole();
        $this->assertSame(2, $sport->current_count);
    }

    #[Test]
    public function it_removes_the_projection_when_the_domain_has_no_active_days_left(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Moto]);

        $this->app->make(UpdateStreaks::class)->handle($user);

        $this->assertSame(0, Streak::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_is_idempotent_across_repeated_runs(): void
    {
        $user = User::factory()->create();
        $this->entryOn($user, GamificationDomain::Sport, 1);
        $this->entryOn($user, GamificationDomain::Sport, 0);
        $action = $this->app->make(UpdateStreaks::class);

        $action->handle($user);
        $action->handle($user);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Streak::query()->where('user_id', $user->id)->sole()->current_count);
    }

    /**
     * Create a ledger entry for the domain the given number of days ago.
     */
    private function entryOn(User $user, GamificationDomain $domain, int $daysAgo): XpEntry
    {
        return XpEntry::factory()->create([
            'user_id' => $user->id,
            'domain' => $domain,
            'points' => 10,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
    }
}
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=UpdateStreaksTest`
Expected: FAIL — `Class "Functional\Gamification\Actions\UpdateStreaks" not found`.

- [ ] **Step 3: Implémenter l'action (sans milestones)**

```php
<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateStreaks
{
    public function __construct(private RefreshPlayerProfile $refreshPlayerProfile) {}

    /**
     * Recompute the user's streak projections from the ledger and refresh the profile.
     */
    public function handle(User $user): LevelTransition
    {
        return DB::transaction(function () use ($user): LevelTransition {
            $runsByDomain = $this->activeDaysByDomain($user)->map(fn (Collection $days): Collection => $this->runs($days));

            $this->syncProjections($user, $runsByDomain);

            return $this->refreshPlayerProfile->handle($user);
        });
    }

    /**
     * Group the distinct non-milestone ledger days by domain, sorted ascending.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function activeDaysByDomain(User $user): Collection
    {
        return XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', '!=', Streak::MILESTONE_RULE_KEY)
            ->selectRaw('DISTINCT domain, DATE(occurred_at) as day')
            ->get()
            ->groupBy(fn (XpEntry $entry): string => $entry->domain->value)
            ->map(fn (Collection $entries): Collection => $entries->pluck('day')->sort()->values());
    }

    /**
     * Split sorted day strings into consecutive runs of start date and length.
     *
     * @param  Collection<int, string>  $days
     * @return Collection<int, array{start: Carbon, length: int}>
     */
    private function runs(Collection $days): Collection
    {
        $runs = collect();
        $start = null;
        $previous = null;
        $length = 0;

        foreach ($days as $day) {
            $date = Carbon::parse($day)->startOfDay();

            if ($previous === null || ! $date->equalTo($previous->copy()->addDay())) {
                if ($start !== null) {
                    $runs->push(['start' => $start, 'length' => $length]);
                }
                $start = $date;
                $length = 0;
            }

            $length++;
            $previous = $date;
        }

        if ($start !== null) {
            $runs->push(['start' => $start, 'length' => $length]);
        }

        return $runs;
    }

    /**
     * Upsert one streak row per active domain and drop the domains without activity.
     *
     * @param  Collection<string, Collection<int, array{start: Carbon, length: int}>>  $runsByDomain
     */
    private function syncProjections(User $user, Collection $runsByDomain): void
    {
        Streak::query()
            ->where('user_id', $user->id)
            ->whereNotIn('domain', $runsByDomain->keys())
            ->delete();

        $now = now();
        $threshold = $now->copy()->subDay()->toDateString();

        $rows = $runsByDomain->map(function (Collection $runs, string $domain) use ($user, $now, $threshold): array {
            $last = $runs->last();
            $lastDay = $last['start']->copy()->addDays($last['length'] - 1);
            $current = $lastDay->toDateString() >= $threshold ? $last['length'] : 0;

            return [
                'id' => strtolower((string) Str::ulid()),
                'user_id' => $user->id,
                'domain' => $domain,
                'current_count' => $current,
                'best_count' => $runs->max('length'),
                'last_activity_date' => $lastDay->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values();

        if ($rows->isNotEmpty()) {
            DB::table('streaks')->upsert(
                $rows->all(),
                ['user_id', 'domain'],
                ['current_count', 'best_count', 'last_activity_date', 'updated_at'],
            );
        }
    }
}
```

Attention Larastan : `day` est un attribut virtuel issu du selectRaw — si Larastan se plaint du `pluck('day')`, utiliser `->map(fn (XpEntry $entry): string => (string) $entry->getAttribute('day'))` à la place.

- [ ] **Step 4: Vérifier que les tests passent**

Run: `php artisan test --compact --filter=UpdateStreaksTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit et push**

```bash
git add functional/gamification/src/Actions/UpdateStreaks.php tests/Feature/Gamification/UpdateStreaksTest.php
git commit -m "✨ recompute per-domain streak projections from the ledger"
git push
```

### Task 4: Bonus XP de palier (7/30/100 jours)

**Files:**
- Modify: `functional/gamification/config/gamification.php`
- Modify: `functional/gamification/src/Actions/UpdateStreaks.php`
- Test: `tests/Feature/Gamification/UpdateStreaksTest.php` (ajout de tests)

**Interfaces:**
- Consumes: `UpdateStreaks` (Task 3), `Streak::MILESTONE_RULE_KEY`, clé unique ledger (`user_id`, `rule_key`, `source_type`, `source_id`).
- Produces: entrées `xp_entries` avec `rule_key = 'streak_milestone'`, `source_type = Streak::class`, `source_id = "{domain}:{startDate}:{days}"`, points depuis `config('gamification.streaks.milestones')`, synchronisées de façon convergente (les paliers non atteints sont supprimés).

- [ ] **Step 1: Ajouter les tests qui échouent**

Ajouter à `UpdateStreaksTest` :

```php
#[Test]
public function it_awards_the_milestone_xp_when_a_run_reaches_seven_days(): void
{
    $user = User::factory()->create();
    foreach (range(0, 6) as $daysAgo) {
        $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
    }

    $this->app->make(UpdateStreaks::class)->handle($user);

    $milestone = XpEntry::query()
        ->where('user_id', $user->id)
        ->where('rule_key', Streak::MILESTONE_RULE_KEY)
        ->sole();
    $this->assertSame(config('gamification.streaks.milestones.7'), $milestone->points);
    $this->assertSame(GamificationDomain::Sport, $milestone->domain);
    $this->assertSame(now()->toDateString(), $milestone->occurred_at->toDateString());
}

#[Test]
public function it_does_not_duplicate_milestones_across_runs(): void
{
    $user = User::factory()->create();
    foreach (range(0, 6) as $daysAgo) {
        $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
    }
    $action = $this->app->make(UpdateStreaks::class);

    $action->handle($user);
    $action->handle($user);

    $this->assertSame(1, XpEntry::query()->where('rule_key', Streak::MILESTONE_RULE_KEY)->count());
}

#[Test]
public function it_removes_the_milestone_when_the_run_no_longer_reaches_it(): void
{
    $user = User::factory()->create();
    foreach (range(0, 6) as $daysAgo) {
        $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
    }
    $action = $this->app->make(UpdateStreaks::class);
    $action->handle($user);

    XpEntry::query()
        ->where('user_id', $user->id)
        ->where('rule_key', '!=', Streak::MILESTONE_RULE_KEY)
        ->where('occurred_at', '>=', now()->subDays(3)->startOfDay())
        ->delete();
    $action->handle($user);

    $this->assertSame(0, XpEntry::query()->where('rule_key', Streak::MILESTONE_RULE_KEY)->count());
}

#[Test]
public function it_ignores_milestone_entries_when_computing_active_days(): void
{
    $user = User::factory()->create();
    foreach (range(0, 6) as $daysAgo) {
        $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
    }
    $action = $this->app->make(UpdateStreaks::class);
    $action->handle($user);

    $action->handle($user);

    $this->assertSame(7, Streak::query()->where('user_id', $user->id)->sole()->current_count);
}

#[Test]
public function it_refreshes_the_player_profile_with_the_milestone_points(): void
{
    $user = User::factory()->create();
    foreach (range(0, 6) as $daysAgo) {
        $this->entryOn($user, GamificationDomain::Sport, $daysAgo);
    }

    $this->app->make(UpdateStreaks::class)->handle($user);

    $expected = 7 * 10 + config('gamification.streaks.milestones.7');
    $this->assertSame($expected, \Functional\Gamification\Models\PlayerProfile::query()->where('user_id', $user->id)->sole()->total_xp);
}
```

(Utiliser un import `use Functional\Gamification\Models\PlayerProfile;` en tête de fichier plutôt que le FQCN inline.)

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=UpdateStreaksTest`
Expected: FAIL — les 5 nouveaux tests échouent (aucune entrée milestone créée), les 7 anciens passent.

- [ ] **Step 3: Ajouter la config des paliers**

Dans `functional/gamification/config/gamification.php`, ajouter après la clé `xp` :

```php
'streaks' => [
    'milestones' => [
        7 => 25,
        30 => 100,
        100 => 400,
    ],
],
```

- [ ] **Step 4: Synchroniser les milestones dans UpdateStreaks**

Dans `UpdateStreaks::handle`, insérer la sync entre `syncProjections` et le refresh :

```php
public function handle(User $user): LevelTransition
{
    return DB::transaction(function () use ($user): LevelTransition {
        $runsByDomain = $this->activeDaysByDomain($user)->map(fn (Collection $days): Collection => $this->runs($days));

        $this->syncProjections($user, $runsByDomain);
        $this->syncMilestones($user, $runsByDomain);

        return $this->refreshPlayerProfile->handle($user);
    });
}
```

Ajouter la méthode privée :

```php
/**
 * Upsert the reached milestone awards and drop the ones no run reaches anymore.
 *
 * @param  Collection<string, Collection<int, array{start: Carbon, length: int}>>  $runsByDomain
 */
private function syncMilestones(User $user, Collection $runsByDomain): void
{
    /** @var array<int, int> $milestones */
    $milestones = config('gamification.streaks.milestones');
    $now = now();

    $rows = $runsByDomain->flatMap(fn (Collection $runs, string $domain): Collection => $runs->flatMap(
        fn (array $run): Collection => collect($milestones)
            ->filter(fn (int $points, int $days): bool => $days <= $run['length'])
            ->map(fn (int $points, int $days): array => [
                'id' => strtolower((string) Str::ulid()),
                'user_id' => $user->id,
                'domain' => $domain,
                'rule_key' => Streak::MILESTONE_RULE_KEY,
                'source_type' => Streak::class,
                'source_id' => $domain.':'.$run['start']->toDateString().':'.$days,
                'points' => $points,
                'occurred_at' => $run['start']->copy()->addDays($days - 1),
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()
    ))->values();

    XpEntry::query()
        ->where('user_id', $user->id)
        ->where('rule_key', Streak::MILESTONE_RULE_KEY)
        ->whereNotIn('source_id', $rows->pluck('source_id'))
        ->delete();

    $rows->chunk(500)->each(function (Collection $chunk): void {
        DB::table('xp_entries')->upsert(
            $chunk->all(),
            ['user_id', 'rule_key', 'source_type', 'source_id'],
            ['points', 'occurred_at', 'updated_at'],
        );
    });
}
```

- [ ] **Step 5: Vérifier que tous les tests passent**

Run: `php artisan test --compact --filter=UpdateStreaksTest`
Expected: PASS (12 tests). Lancer aussi `php artisan test --compact --filter=AwardXpTest` pour vérifier l'absence de régression.

- [ ] **Step 6: Commit et push**

```bash
git add functional/gamification/config/gamification.php functional/gamification/src/Actions/UpdateStreaks.php tests/Feature/Gamification/UpdateStreaksTest.php
git commit -m "✨ award configurable streak milestone xp bonuses"
git push
```

### Task 5: Câbler UpdateStreaks dans l'orchestrateur

**Files:**
- Modify: `functional/gamification/src/Actions/RunUserGamification.php`
- Test: `tests/Feature/Gamification/ProcessUserGamificationJobTest.php` (ajout d'un test)

**Interfaces:**
- Consumes: `UpdateStreaks::handle(User $user): LevelTransition` (Tasks 3-4), `AwardXp::handle(...): LevelTransition` (existant).
- Produces: `RunUserGamification::handle(User $user, ?Carbon $since = null): LevelTransition` met désormais à jour les streaks après le ledger XP ; la transition retournée couvre le niveau avant règles → niveau après milestones.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `ProcessUserGamificationJobTest` (suivre les helpers existants du fichier pour créer les données source — il crée déjà des `SportActivity` pour tester le job ; réutiliser le même pattern) :

```php
#[Test]
public function it_updates_the_streak_projections_after_the_ledger(): void
{
    $user = User::factory()->create();
    SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subDay()]);
    SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

    (new ProcessUserGamificationJob($user->id))->handle($this->app->make(RunUserGamification::class));

    $streak = Streak::query()->where('user_id', $user->id)->where('domain', GamificationDomain::Sport->value)->sole();
    $this->assertSame(2, $streak->current_count);
}
```

Ajouter les imports nécessaires (`Streak`, `GamificationDomain`, et ceux déjà présents dans le fichier). Adapter la ligne d'exécution du job au pattern exact déjà utilisé dans ce fichier (dispatch synchrone ou `handle()` direct — reprendre l'existant).

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=it_updates_the_streak_projections_after_the_ledger`
Expected: FAIL — aucune ligne `streaks` créée.

- [ ] **Step 3: Câbler l'action**

```php
<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;

class RunUserGamification
{
    public function __construct(
        private AwardXp $awardXp,
        private UpdateStreaks $updateStreaks,
    ) {}

    /**
     * Run every tagged xp rule for the user then refresh the streaks, widening to the full history when the ledger is empty.
     */
    public function handle(User $user, ?Carbon $since = null): LevelTransition
    {
        if ($since !== null && ! XpEntry::query()->where('user_id', $user->id)->exists()) {
            $since = null;
        }

        $windowStart = $since?->copy()->startOfDay();

        $rules = collect(app()->tagged('gamification.xp_rules'));
        $awards = $rules->flatMap(fn (XpRule $rule) => $rule->awards($user, $windowStart));
        $ruleKeys = $rules->map(fn (XpRule $rule): string => $rule->key())->values();

        $xpTransition = $this->awardXp->handle($user, $awards, $ruleKeys, $windowStart);
        $streakTransition = $this->updateStreaks->handle($user);

        return new LevelTransition($xpTransition->previousLevel, $streakTransition->currentLevel);
    }
}
```

- [ ] **Step 4: Vérifier que la suite du job passe**

Run: `php artisan test --compact --filter=ProcessUserGamificationJobTest`
Expected: PASS. ATTENTION : `AwardXp::purgeWindow` supprime toutes les entrées de la fenêtre y compris les milestones — c'est voulu, `UpdateStreaks` les resynchronise juste après dans le même run. Si un test existant du job casse sur un total XP, vérifier s'il doit maintenant inclure un bonus milestone (7+ jours consécutifs de données de test) et corriger l'attendu.

- [ ] **Step 5: Commit et push**

```bash
git add functional/gamification/src/Actions/RunUserGamification.php tests/Feature/Gamification/ProcessUserGamificationJobTest.php
git commit -m "✨ refresh streaks after each gamification run"
git push
```

### Task 6: API REST lecture seule pour les streaks

**Files:**
- Create: `functional/gamification/src/Rest/Resource/StreakResource.php`
- Create: `functional/gamification/src/Rest/Controller/StreaksController.php`
- Create: `functional/gamification/src/Rest/Controls/StreakControl.php`
- Create: `functional/gamification/src/Rest/Policies/StreakPolicy.php`
- Modify: `functional/gamification/routes/api.php`
- Modify: `functional/gamification/src/Providers/GamificationServiceProvider.php`
- Test: `tests/Feature/Gamification/GamificationApiScopeTest.php` (ajout de tests)

**Interfaces:**
- Consumes: `Streak` (Task 2), base classes `Technical\Osdd\Rest\...` (mêmes que `PlayerProfileResource`/`PlayerProfilesController`).
- Produces: endpoint `POST /api/streaks/search` scopé par user, mutations interdites.

- [ ] **Step 1: Ajouter les tests qui échouent**

Ajouter à `GamificationApiScopeTest` :

```php
#[Test]
public function it_only_returns_the_authenticated_users_streaks(): void
{
    $user = User::factory()->create();
    Streak::factory()->create(['user_id' => $user->id, 'current_count' => 4]);
    Streak::factory()->create();

    $response = $this->actingAs($user, 'api')->postJson('/api/streaks/search', [
        'search' => [],
    ]);

    $response->assertOk();
    $this->assertCount(1, $response->json('data'));
    $this->assertSame(4, $response->json('data.0.current_count'));
}

#[Test]
public function it_rejects_updating_own_streaks_through_the_api(): void
{
    $user = User::factory()->create();
    $streak = Streak::factory()->create(['user_id' => $user->id, 'current_count' => 2]);

    $response = $this->actingAs($user, 'api')->postJson('/api/streaks/mutate', [
        'mutate' => [
            [
                'operation' => 'update',
                'key' => $streak->id,
                'attributes' => ['current_count' => 999],
            ],
        ],
    ]);

    $response->assertForbidden();
    $this->assertSame(2, $streak->fresh()->current_count);
}
```

Ajouter l'import `use Functional\Gamification\Models\Streak;` et étendre `it_requires_authentication` :

```php
$this->postJson('/api/streaks/search', ['search' => []])->assertUnauthorized();
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=GamificationApiScopeTest`
Expected: FAIL — 404 sur `/api/streaks/search`.

- [ ] **Step 3: Créer la ressource REST**

```php
<?php

namespace Functional\Gamification\Rest\Resource;

use Functional\Gamification\Models\Streak;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class StreakResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Streak::class;

    /**
     * The exposed fields that could be provided.
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'domain',
            'current_count',
            'best_count',
            'last_activity_date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * The exposed relations that could be provided.
     *
     * @return array<int, mixed>
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed scopes that could be provided.
     *
     * @return array<int, mixed>
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided.
     *
     * @return array<int, int>
     */
    public function limits(RestRequest $request): array
    {
        return [10, 25, 50, 100];
    }

    /**
     * The exposed default order applied to the resource.
     *
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['current_count' => 'desc'];
    }

    /**
     * The actions that should be linked.
     *
     * @return array<int, mixed>
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked.
     *
     * @return array<int, mixed>
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Créer le controller, le control et la policy**

`StreaksController` :

```php
<?php

namespace Functional\Gamification\Rest\Controller;

use Functional\Gamification\Rest\Resource\StreakResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Concerns\RejectsApiCreation;
use Technical\Osdd\Rest\Controllers\Controller;

class StreaksController extends Controller
{
    use RejectsApiCreation;

    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = StreakResource::class;
}
```

`StreakControl` :

```php
<?php

namespace Functional\Gamification\Rest\Controls;

use Functional\Gamification\Models\Streak;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class StreakControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model = Streak::class;

    /**
     * Restrict access to the streaks owned by the authenticated user.
     *
     * @return array<int, Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query->where('user_id', $user->getKey()))
                ->should(fn (Model $user, Model $model): bool => $model->getAttribute('user_id') === $user->getKey()),
        ];
    }
}
```

`StreakPolicy` :

```php
<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\StreakControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class StreakPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of streaks.
     *
     * @var class-string<Control>
     */
    protected string $control = StreakControl::class;

    /**
     * Forbid updating the recomputed projection through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting the recomputed projection through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Enregistrer la route et la policy**

Dans `routes/api.php`, ajouter dans le groupe `auth:api` (avec l'import du controller) :

```php
Rest::resource('streaks', StreaksController::class);
```

Dans `GamificationServiceProvider::boot`, à côté des lignes existantes (avec les imports `Streak`, `StreakControl`, `StreakPolicy`) :

```php
(new Access)->addControl(new StreakControl);
Gate::policy(Streak::class, StreakPolicy::class);
```

- [ ] **Step 6: Vérifier que les tests passent**

Run: `php artisan test --compact --filter=GamificationApiScopeTest`
Expected: PASS (9 tests).

- [ ] **Step 7: Commit et push**

```bash
git add functional/gamification tests/Feature/Gamification/GamificationApiScopeTest.php
git commit -m "✨ expose read-only streaks through the rest api"
git push
```

### Task 7: UI — hub Joueur et tuile dashboard

**Files:**
- Modify: `functional/gamification/src/Livewire/PlayerProfilePage.php`
- Modify: `functional/gamification/resources/views/player.blade.php`
- Modify: `functional/gamification/src/Dashboard/GamificationDashboardContribution.php`
- Test: `tests/Feature/Gamification/PlayerProfilePageTest.php`, `tests/Feature/Gamification/GamificationDashboardContributionTest.php` (ajouts)

**Interfaces:**
- Consumes: `Streak` (Task 2), `GamificationDomain::label()/color()/icon()`, composants `x-ui.glass-card`, pattern accents de `player.blade.php`.
- Produces: section « Séries » sur la page Joueur (visible seulement si au moins un streak existe), ligne « Série {label} : N j » sur la tuile dashboard quand un streak courant > 0 existe.

- [ ] **Step 1: Ajouter les tests qui échouent**

Dans `PlayerProfilePageTest` (suivre le style du fichier existant — il rend la page via Livewire ou HTTP ; reprendre le pattern) :

```php
#[Test]
public function it_shows_the_user_streaks(): void
{
    $user = User::factory()->create();
    Streak::factory()->create([
        'user_id' => $user->id,
        'domain' => GamificationDomain::Sport,
        'current_count' => 12,
        'best_count' => 20,
        'last_activity_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('player'))
        ->assertOk()
        ->assertSee('Séries')
        ->assertSee('12')
        ->assertSee('Record : 20');
}

#[Test]
public function it_hides_the_streak_section_when_the_user_has_none(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('player'))
        ->assertOk()
        ->assertDontSee('Record :');
}
```

Dans `GamificationDashboardContributionTest` (suivre le style existant qui instancie la contribution et lit `dashboardSummary`) :

```php
#[Test]
public function it_surfaces_the_hottest_streak_on_the_summary(): void
{
    $user = User::factory()->create();
    Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'current_count' => 3]);
    Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Todo, 'current_count' => 9]);

    $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

    $this->assertContains('Série Tâches : 9 j', $summary->secondaryLines);
}

#[Test]
public function it_omits_the_streak_line_without_a_live_streak(): void
{
    $user = User::factory()->create();
    Streak::factory()->create(['user_id' => $user->id, 'current_count' => 0]);

    $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

    $this->assertCount(3, $summary->secondaryLines);
}
```

Ajouter les imports (`Streak`, `GamificationDomain`) dans les deux fichiers.

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter="PlayerProfilePageTest|GamificationDashboardContributionTest"`
Expected: FAIL — les 4 nouveaux tests échouent.

- [ ] **Step 3: Passer les streaks à la page Joueur**

Dans `PlayerProfilePage::render`, ajouter l'import `use Functional\Gamification\Models\Streak;` puis dans le tableau passé à la vue :

```php
'streaks' => Streak::query()
    ->where('user_id', $user->id)
    ->orderByDesc('current_count')
    ->get(),
```

- [ ] **Step 4: Ajouter la section Séries à la vue**

Dans `player.blade.php`, insérer entre la section stat-tiles et la carte « XP par jour » (réutiliser la map `$accents` déjà définie plus bas dans le fichier en la remontant au-dessus de cette section via `@php ... @endphp`) :

```blade
@if ($streaks->isNotEmpty())
    <section class="mt-8" style="animation-delay: 0.15s;">
        <h3 class="font-display text-lg font-semibold tracking-tight">Séries</h3>
        <p class="mt-0.5 text-sm text-muted">Vos jours consécutifs d'activité par domaine.</p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($streaks as $streak)
                @php
                    $accent = $accents[$streak->domain->color()] ?? $accents['cyan'];
                    $alive = $streak->last_activity_date !== null && $streak->last_activity_date->gte(now()->subDay()->startOfDay());
                @endphp
                <x-ui.glass-card hover padding="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $accent['bg'] }} {{ $accent['text'] }}">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="{{ $streak->domain->icon() }}" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $streak->domain->label() }}</p>
                                <p class="font-display text-xl font-bold {{ $alive ? $accent['text'] : 'text-muted' }}">
                                    {{ $streak->current_count }} <span class="text-sm font-normal text-muted">j</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            @if ($alive)
                                <span class="text-lg" title="Série en cours">🔥</span>
                            @endif
                            <p class="text-xs text-faint">Record : {{ $streak->best_count }}</p>
                        </div>
                    </div>
                </x-ui.glass-card>
            @endforeach
        </div>
    </section>
@endif
```

Note : la map `$accents` est actuellement définie dans la section « XP par domaine » plus bas — déplacer son bloc `@php` au-dessus de la nouvelle section pour qu'elle serve aux deux, sans la dupliquer.

- [ ] **Step 5: Ajouter la ligne série à la tuile dashboard**

Dans `GamificationDashboardContribution::dashboardSummary`, ajouter l'import `use Functional\Gamification\Models\Streak;` puis avant le `return` :

```php
$hottest = Streak::query()
    ->where('user_id', $user->getAuthIdentifier())
    ->where('current_count', '>', 0)
    ->orderByDesc('current_count')
    ->first();

$secondaryLines = [
    number_format($totalXp, 0, ',', ' ').' XP au total',
    number_format($remaining, 0, ',', ' ').' XP avant le niveau '.($level + 1),
    number_format($monthlyXp, 0, ',', ' ').' XP ce mois-ci',
];

if ($hottest !== null) {
    $secondaryLines[] = 'Série '.$hottest->domain->label().' : '.$hottest->current_count.' j';
}
```

et remplacer le tableau littéral `secondaryLines: [...]` par `secondaryLines: $secondaryLines`.

- [ ] **Step 6: Vérifier que les tests passent**

Run: `php artisan test --compact --filter="PlayerProfilePageTest|GamificationDashboardContributionTest"`
Expected: PASS.

- [ ] **Step 7: Commit et push**

```bash
git add functional/gamification tests/Feature/Gamification
git commit -m "✨ surface streaks on the player hub and dashboard tile"
git push
```

### Task 8: Vérification finale et PR

**Files:**
- Test: suite complète.

**Interfaces:**
- Consumes: Tasks 2-7 terminées.
- Produces: branche verte, PR ouverte vers `develop`.

- [ ] **Step 1: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: aucun changement (sinon committer `🎨 apply pint formatting` et pousser).

- [ ] **Step 2: Larastan**

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: `[OK] No errors`. Corriger toute erreur avant de continuer (commits dédiés).

- [ ] **Step 3: Suite complète**

```bash
php artisan test --compact
```

Expected: 100% verte (≥ 375 tests existants + les nouveaux).

- [ ] **Step 4: Ouvrir la PR**

```bash
gh pr create --base develop --head feature/gamification-phase-2 \
  --title "✨ Gamification phase 2: per-domain streaks with milestone xp" \
  --body "$(cat <<'EOF'
## Summary
- streak projections per gamification domain, recomputed idempotently from the xp ledger
- configurable milestone bonuses (7/30/100 days) written as convergent ledger entries
- streaks wired into the gamification run, purged on user deletion
- read-only REST endpoint /api/streaks, player hub section and dashboard tile line

## Test plan
- [ ] php artisan test --compact (full suite green)
- [ ] vendor/bin/phpstan analyse (no errors)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Expected: URL de PR retournée. Le merge reste à la main de l'utilisateur (protection `develop`).

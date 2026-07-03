<?php

namespace Functional\Gamification\Providers;

use Functional\Exploration\Events\CoverageRebuilt;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Gamification\Console\BackfillGamification;
use Functional\Gamification\Console\RecalculateGamification;
use Functional\Gamification\Dashboard\GamificationDashboardContribution;
use Functional\Gamification\Database\Seeders\GamificationSeeder;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Listeners\DeleteUserGamificationData;
use Functional\Gamification\Listeners\ProcessXpOnSync;
use Functional\Gamification\Livewire\PlayerProfilePage;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Rest\Controls\PlayerProfileControl;
use Functional\Gamification\Rest\Controls\XpEntryControl;
use Functional\Gamification\Rest\Policies\PlayerProfilePolicy;
use Functional\Gamification\Rest\Policies\XpEntryPolicy;
use Functional\Gamification\Xp\Rules\ExplorationCellXpRule;
use Functional\Gamification\Xp\Rules\FinanceMonthlyXpRule;
use Functional\Gamification\Xp\Rules\HealthMeasurementDayXpRule;
use Functional\Gamification\Xp\Rules\MotoRideXpRule;
use Functional\Gamification\Xp\Rules\SportActivityXpRule;
use Functional\Gamification\Xp\Rules\TodoTaskCompletedXpRule;
use Functional\Health\Events\WithingsMeasurementsSynced;
use Functional\Sport\Events\StravaActivitiesSynced;
use Functional\Users\Events\UserDeleting;
use Functional\Users\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class GamificationServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        StravaActivitiesSynced::class => [
            ProcessXpOnSync::class,
        ],
        WithingsMeasurementsSynced::class => [
            ProcessXpOnSync::class,
        ],
        BankTransactionsSynced::class => [
            ProcessXpOnSync::class,
        ],
        CoverageRebuilt::class => [
            ProcessXpOnSync::class,
        ],
        UserDeleting::class => [
            DeleteUserGamificationData::class,
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/gamification.php', 'gamification');

        $this->app->tag([
            SportActivityXpRule::class,
            HealthMeasurementDayXpRule::class,
            FinanceMonthlyXpRule::class,
            MotoRideXpRule::class,
            TodoTaskCompletedXpRule::class,
            ExplorationCellXpRule::class,
        ], 'gamification.xp_rules');

        $this->app->tag(GamificationDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'gamification');

        Blade::anonymousComponentNamespace('gamification::components', 'gamification');

        Livewire::component('gamification-player-profile', PlayerProfilePage::class);

        (new Access)->addControl(new XpEntryControl);
        (new Access)->addControl(new PlayerProfileControl);

        Gate::policy(XpEntry::class, XpEntryPolicy::class);
        Gate::policy(PlayerProfile::class, PlayerProfilePolicy::class);

        $this->loadListenEvent();

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([GamificationSeeder::class]);
            $this->commands([BackfillGamification::class, RecalculateGamification::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->call(function (): void {
                    User::query()
                        ->cursor()
                        ->each(fn (User $user) => ProcessUserGamificationJob::dispatch($user->id, now()->subDays(3)));
                })->dailyAt('02:00');
            });
        }
    }
}

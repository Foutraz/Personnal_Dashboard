<?php

namespace Functional\Gamification\Providers;

use Functional\Exploration\Events\CoverageRebuilt;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Gamification\Console\BackfillGamification;
use Functional\Gamification\Console\RecalculateGamification;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Listeners\DeleteUserGamificationData;
use Functional\Gamification\Listeners\ProcessXpOnSync;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadListenEvent();

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
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

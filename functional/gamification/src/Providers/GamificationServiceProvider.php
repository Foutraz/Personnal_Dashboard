<?php

namespace Functional\Gamification\Providers;

use Functional\Gamification\Xp\Rules\ExplorationCellXpRule;
use Functional\Gamification\Xp\Rules\FinanceMonthlyXpRule;
use Functional\Gamification\Xp\Rules\HealthMeasurementDayXpRule;
use Functional\Gamification\Xp\Rules\MotoRideXpRule;
use Functional\Gamification\Xp\Rules\SportActivityXpRule;
use Functional\Gamification\Xp\Rules\TodoTaskCompletedXpRule;
use Technical\Osdd\Providers\OsddServiceProvider;

class GamificationServiceProvider extends OsddServiceProvider
{
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
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }
}

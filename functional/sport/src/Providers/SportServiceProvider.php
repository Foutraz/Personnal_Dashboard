<?php

namespace Functional\Sport\Providers;

use Foutraz\Strava\StravaManager;
use Functional\Sport\Database\Seeders\SportSeeder;
use Functional\Sport\Listeners\DeleteConnectionSportActivities;
use Functional\Sport\Livewire\ActivitiesHistory;
use Functional\Sport\Livewire\ConnectStrava;
use Functional\Sport\Livewire\PerformanceAnalysis;
use Functional\Sport\Livewire\SportDashboard;
use Functional\Sport\Livewire\SportStatistics;
use Functional\Sport\Rest\Controls\SportActivityControl;
use Illuminate\Contracts\Container\BindingResolutionException;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Providers\OsddServiceProvider;

class SportServiceProvider extends OsddServiceProvider
{
    /**
     * Register any application services.
     *
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/sport.php', 'sport');

        $this->app->bind(StravaManager::class, fn (): StravaManager => new StravaManager(
            (string) config('strava.endpoint'),
            (string) config('strava.token'),
            (string) config('strava.client_id'),
            (string) config('strava.client_secret'),
            (string) config('strava.redirect_uri'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'sport');

        (new Access)->addControl(new SportActivityControl);

        IntegrationConnection::deleting(fn (IntegrationConnection $connection) => app(DeleteConnectionSportActivities::class)->handle($connection));

        Livewire::component('sport-dashboard', SportDashboard::class);
        Livewire::component('connect-strava', ConnectStrava::class);
        Livewire::component('activities-history', ActivitiesHistory::class);
        Livewire::component('sport-statistics', SportStatistics::class);
        Livewire::component('performance-analysis', PerformanceAnalysis::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([SportSeeder::class]);
        }
    }
}

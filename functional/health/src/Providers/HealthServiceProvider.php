<?php

namespace Functional\Health\Providers;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Database\Seeders\HealthSeeder;
use Functional\Health\Jobs\SyncWithingsMeasurementsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Providers\OsddServiceProvider;

class HealthServiceProvider extends OsddServiceProvider
{
    /**
     * Register any application services.
     *
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/health.php', 'health');

        $this->app->bind(WithingsManager::class, fn (): WithingsManager => new WithingsManager(
            (string) config('health.endpoint'),
            (string) config('health.token'),
            (string) config('health.client_id'),
            (string) config('health.client_secret'),
            (string) config('health.redirect_uri'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'health');

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([HealthSeeder::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->call(function (): void {
                    IntegrationConnection::query()
                        ->where('provider', IntegrationProvider::Withings)
                        ->each(fn (IntegrationConnection $connection) => SyncWithingsMeasurementsJob::dispatch($connection->id));
                })->daily();
            });
        }
    }
}

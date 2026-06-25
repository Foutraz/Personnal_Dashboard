<?php

namespace Functional\Exploration\Providers;

use Functional\Exploration\Console\RebuildCoverage;
use Functional\Exploration\Database\Seeders\ExplorationSeeder;
use Functional\Exploration\Listeners\DeleteUserExploredCells;
use Functional\Exploration\Livewire\ExplorationDashboard;
use Functional\Exploration\Rest\Controls\ExploredCellControl;
use Functional\Users\Events\UserDeleting;
use Illuminate\Contracts\Container\BindingResolutionException;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class ExplorationServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserExploredCells::class,
        ],
    ];

    /**
     * Register any application services.
     *
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/exploration.php', 'exploration');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'exploration');

        (new Access)->addControl(new ExploredCellControl);

        $this->loadListenEvent();

        Livewire::component('exploration-dashboard', ExplorationDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RebuildCoverage::class]);
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([ExplorationSeeder::class]);
        }
    }
}

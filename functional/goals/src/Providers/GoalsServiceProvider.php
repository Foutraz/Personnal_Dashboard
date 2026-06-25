<?php

namespace Functional\Goals\Providers;

use Functional\Goals\Actions\AssignGoalOwner;
use Functional\Goals\Database\Seeders\GoalsSeeder;
use Functional\Goals\Listeners\DeleteUserGoals;
use Functional\Goals\Livewire\GoalsDashboard;
use Functional\Goals\Models\Goal;
use Functional\Goals\Rest\Controls\GoalControl;
use Functional\Users\Events\UserDeleting;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class GoalsServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserGoals::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/goals.php', 'goals');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'goals');

        Blade::anonymousComponentNamespace('goals::components', 'goals');

        (new Access)->addControl(new GoalControl);

        $this->loadListenEvent();

        Goal::creating(fn (Goal $goal) => app(AssignGoalOwner::class)->handle($goal));

        Livewire::component('goals-dashboard', GoalsDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([GoalsSeeder::class]);
        }
    }
}

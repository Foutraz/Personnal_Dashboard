<?php

namespace Functional\Todo\Providers;

use Functional\Todo\Actions\AssignTaskOwner;
use Functional\Todo\Console\SendDueTaskReminders;
use Functional\Todo\Database\Seeders\TodoSeeder;
use Functional\Todo\Listeners\DeleteTaskReminders;
use Functional\Todo\Listeners\DeleteUserTasks;
use Functional\Todo\Livewire\TodoBoard;
use Functional\Todo\Livewire\TodoStatistics;
use Functional\Todo\Models\Task;
use Functional\Todo\Rest\Controls\TaskControl;
use Functional\Users\Events\UserDeleting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class TodoServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserTasks::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/todo.php', 'todo');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'todo');

        (new Access)->addControl(new TaskControl);

        $this->loadListenEvent();

        Task::creating(fn (Task $task) => app(AssignTaskOwner::class)->handle($task));
        Task::deleting(fn (Task $task) => app(DeleteTaskReminders::class)->handle($task));

        Livewire::component('todo-board', TodoBoard::class);
        Livewire::component('todo-statistics', TodoStatistics::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([TodoSeeder::class]);
            $this->commands([SendDueTaskReminders::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command(SendDueTaskReminders::class)->everyFifteenMinutes();
            });
        }
    }
}

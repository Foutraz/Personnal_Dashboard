<?php

namespace Functional\RecurringExpenses\Providers;

use Functional\RecurringExpenses\Actions\AssignExpenseOwner;
use Functional\RecurringExpenses\Console\SendDueExpenseReminders;
use Functional\RecurringExpenses\Database\Seeders\RecurringExpensesSeeder;
use Functional\RecurringExpenses\Listeners\DeleteExpenseReminders;
use Functional\RecurringExpenses\Listeners\DeleteUserRecurringExpenses;
use Functional\RecurringExpenses\Livewire\ExpensesDashboard;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Rest\Controls\RecurringExpenseControl;
use Functional\Users\Events\UserDeleting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class RecurringExpensesServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserRecurringExpenses::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/recurring-expenses.php', 'recurring-expenses');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'expenses');

        (new Access)->addControl(new RecurringExpenseControl);

        $this->loadListenEvent();

        RecurringExpense::creating(fn (RecurringExpense $expense) => app(AssignExpenseOwner::class)->handle($expense));
        RecurringExpense::deleting(fn (RecurringExpense $expense) => app(DeleteExpenseReminders::class)->handle($expense));

        Livewire::component('expenses-dashboard', ExpensesDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([RecurringExpensesSeeder::class]);
            $this->commands([SendDueExpenseReminders::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command(SendDueExpenseReminders::class)->dailyAt('08:00');
            });
        }
    }
}

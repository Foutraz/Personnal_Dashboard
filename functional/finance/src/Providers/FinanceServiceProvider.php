<?php

namespace Functional\Finance\Providers;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Actions\AssignPositionOwner;
use Functional\Finance\Actions\AssignTransactionOwner;
use Functional\Finance\Dashboard\FinanceDashboardContribution;
use Functional\Finance\Database\Seeders\FinanceSeeder;
use Functional\Finance\Jobs\SyncBankAccountsJob;
use Functional\Finance\Listeners\DeletePositionTransactions;
use Functional\Finance\Listeners\DeleteUserFinanceData;
use Functional\Finance\Livewire\DcaSimulatorPanel;
use Functional\Finance\Livewire\PerformanceChart;
use Functional\Finance\Livewire\PortfolioOverview;
use Functional\Finance\Livewire\ProjectionsPanel;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Finance\Rest\Controls\InvestmentTransactionControl;
use Functional\Finance\Rest\Controls\PositionControl;
use Functional\Finance\Rest\Policies\InvestmentTransactionPolicy;
use Functional\Finance\Rest\Policies\PositionPolicy;
use Functional\Users\Events\UserDeleting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Providers\OsddServiceProvider;

class FinanceServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserFinanceData::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/finance.php', 'finance');

        $this->app->tag(FinanceDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);

        $this->app->bind(GoCardlessManager::class, fn (): GoCardlessManager => new GoCardlessManager(
            (string) config('finance.gocardless.endpoint'),
            (string) config('finance.gocardless.secret_id'),
            (string) config('finance.gocardless.secret_key'),
            (string) config('finance.gocardless.redirect_uri'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'finance');

        (new Access)->addControl(new PositionControl);
        (new Access)->addControl(new InvestmentTransactionControl);

        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(InvestmentTransaction::class, InvestmentTransactionPolicy::class);

        $this->loadListenEvent();

        Position::creating(fn (Position $position) => app(AssignPositionOwner::class)->handle($position));
        Position::deleting(fn (Position $position) => app(DeletePositionTransactions::class)->handle($position));
        InvestmentTransaction::creating(fn (InvestmentTransaction $transaction) => app(AssignTransactionOwner::class)->handle($transaction));

        Livewire::component('finance-portfolio-overview', PortfolioOverview::class);
        Livewire::component('finance-performance-chart', PerformanceChart::class);
        Livewire::component('finance-dca-simulator-panel', DcaSimulatorPanel::class);
        Livewire::component('finance-projections-panel', ProjectionsPanel::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([FinanceSeeder::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->call(function (): void {
                    IntegrationConnection::query()
                        ->where('provider', IntegrationProvider::GoCardless)
                        ->each(fn (IntegrationConnection $connection) => SyncBankAccountsJob::dispatch($connection->id));
                })->daily();
            });
        }
    }
}

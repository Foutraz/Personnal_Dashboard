<?php

namespace Functional\Planning\Providers;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Foutraz\Outlook\OutlookManager;
use Functional\Planning\Dashboard\PlanningDashboardContribution;
use Functional\Planning\Database\Seeders\PlanningSeeder;
use Functional\Planning\Listeners\DeleteConnectionCalendarEvents;
use Functional\Planning\Listeners\DeleteUserCalendarEvents;
use Functional\Planning\Livewire\PlanningDashboard;
use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Rest\Controls\CalendarEventControl;
use Functional\Planning\Rest\Policies\CalendarEventPolicy;
use Functional\Users\Events\UserDeleting;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Providers\OsddServiceProvider;

class PlanningServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserCalendarEvents::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/planning.php', 'planning');

        $this->app->bind(GoogleCalendarManager::class, fn (): GoogleCalendarManager => new GoogleCalendarManager(
            (string) config('planning.google.endpoint'),
            '',
            (string) config('planning.google.client_id'),
            (string) config('planning.google.client_secret'),
            (string) config('planning.google.redirect_uri'),
        ));

        $this->app->bind(OutlookManager::class, fn (): OutlookManager => new OutlookManager(
            (string) config('planning.outlook.endpoint'),
            '',
            (string) config('planning.outlook.client_id'),
            (string) config('planning.outlook.client_secret'),
            (string) config('planning.outlook.redirect_uri'),
            (string) config('planning.outlook.tenant'),
        ));

        $this->app->tag(PlanningDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'planning');

        (new Access)->addControl(new CalendarEventControl);

        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);

        $this->loadListenEvent();

        IntegrationConnection::deleting(fn (IntegrationConnection $connection) => app(DeleteConnectionCalendarEvents::class)->handle($connection));

        Livewire::component('planning-dashboard', PlanningDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([PlanningSeeder::class]);
        }
    }
}

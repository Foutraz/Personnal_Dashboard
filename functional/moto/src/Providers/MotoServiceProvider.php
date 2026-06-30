<?php

namespace Functional\Moto\Providers;

use Foutraz\Weather\WeatherManager;
use Functional\Moto\Actions\AssignRideOwner;
use Functional\Moto\Dashboard\MotoAgendaProvider;
use Functional\Moto\Dashboard\MotoDashboardContribution;
use Functional\Moto\Database\Seeders\MotoSeeder;
use Functional\Moto\Listeners\DeleteUserMotoRides;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
use Functional\Moto\Rest\Controls\MotoRideControl;
use Functional\Moto\Rest\Policies\MotoRidePolicy;
use Functional\Users\Events\UserDeleting;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class MotoServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserDeleting::class => [
            DeleteUserMotoRides::class,
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

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/moto.php', 'moto');

        $this->app->singleton(WeatherManager::class, fn (): WeatherManager => new WeatherManager(
            (string) config('weather.endpoint'),
            (string) config('weather.api_key'),
        ));

        $this->app->tag(MotoDashboardContribution::class, ['dashboard.summaries', 'dashboard.navigation']);
        $this->app->tag(MotoAgendaProvider::class, ['dashboard.agenda']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'moto');

        (new Access)->addControl(new MotoRideControl);

        Gate::policy(MotoRide::class, MotoRidePolicy::class);

        $this->loadListenEvent();

        MotoRide::creating(fn (MotoRide $ride) => app(AssignRideOwner::class)->handle($ride));

        Livewire::component('moto-dashboard', MotoDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([MotoSeeder::class]);
        }
    }
}

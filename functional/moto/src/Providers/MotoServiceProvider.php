<?php

namespace Functional\Moto\Providers;

use Foutraz\Weather\WeatherManager;
use Functional\Moto\Actions\AssignRideOwner;
use Functional\Moto\Database\Seeders\MotoSeeder;
use Functional\Moto\Listeners\DeleteUserMotoRides;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
use Functional\Moto\Rest\Controls\MotoRideControl;
use Functional\Users\Events\UserDeleting;
use Illuminate\Contracts\Container\BindingResolutionException;
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

        $this->loadListenEvent();

        MotoRide::creating(fn (MotoRide $ride) => app(AssignRideOwner::class)->handle($ride));

        Livewire::component('moto-dashboard', MotoDashboard::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([MotoSeeder::class]);
        }
    }
}

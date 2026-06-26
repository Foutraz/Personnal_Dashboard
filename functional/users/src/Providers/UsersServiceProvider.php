<?php

namespace Functional\Users\Providers;

use Functional\Users\Database\Seeders\UsersSeeder;
use Functional\Users\Events\UserDeleting;
use Functional\Users\Models\User;
use Functional\Users\Rest\Controls\UserControl;
use Functional\Users\Rest\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Technical\Osdd\Providers\OsddServiceProvider;

class UsersServiceProvider extends OsddServiceProvider
{
    public function boot(): void
    {
        User::deleting(fn (User $user) => UserDeleting::dispatch($user));

        (new Access)->addControl(new UserControl);

        Gate::policy(User::class, UserPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([UsersSeeder::class]);
            $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        }
    }

    public function register(): void
    {
        //
    }
}

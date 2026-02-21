<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $guard = 'api';
            $admin = Role::findOrCreate('admin', $guard);
            $user  = Role::findOrCreate('user', $guard);

            $allPermissions = Permission::query()->where('guard_name', $guard)->get();
            $admin->syncPermissions($allPermissions);

            $ownPermissions = Permission::query()->where('guard_name', $guard)
                ->where('name', 'like', '%.own.%')
                ->get();

            $user->syncPermissions($ownPermissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $guard = 'api';
            Role::query()->where('name', 'admin')->where('guard_name', $guard)->delete();
            Role::query()->where('name', 'user')->where('guard_name', $guard)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

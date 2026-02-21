<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modelKeys = array_keys(config('access_models', []));

        DB::transaction(function () use ($modelKeys) {
            $abilities = [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
                'sync',
                'import',
            ];
            $perimeters = ['global', 'own'];
            $guard = 'api';
            foreach ($modelKeys as $modelKey) {
                foreach ($perimeters as $perimeter) {
                    foreach ($abilities as $ability) {
                        $name = "$ability $perimeter $modelKey";
                        Permission::findOrCreate($name, $guard);
                    }
                }
            }

            $specials = ['connexion', 'settings'];
            foreach ($specials as $special) {
                foreach ($perimeters as $perimeter) {
                    $name = "manage $perimeter $special";
                    Permission::findOrCreate($name, $guard);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modelKeys = array_keys(config('access_models', []));

        DB::transaction(function () use ($modelKeys) {
            $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'sync', 'import'];
            $perimeters = ['global', 'own'];
            $guard = 'api';
            foreach ($modelKeys as $modelKey) {
                foreach ($perimeters as $perimeter) {
                    foreach ($abilities as $ability) {
                        Permission::query()->where('name', "$ability $perimeter $modelKey")
                            ->where('guard_name', $guard)
                            ->delete();
                    }
                }
            }

            foreach (['connexion','settings'] as $special) {
                foreach ($perimeters as $perimeter) {
                    Permission::query()->where('name', "manage $perimeter $special")
                        ->where('guard_name', $guard)
                        ->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

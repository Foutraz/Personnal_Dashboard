<?php

namespace Tests\Feature\Lomkit;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Http\Controllers\Controller as RestController;
use Lomkit\Rest\Http\Requests\RestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Technical\Osdd\Rest\Resources\Resource;
use Tests\TestCase;

class ServerManagedFieldsArchitectureTest extends TestCase
{
    public static function restResources(): array
    {
        $root = dirname(__DIR__, 3);
        $files = Finder::create()
            ->files()
            ->in(["{$root}/functional", "{$root}/technical"])
            ->exclude('vendor')
            ->name('*.php')
            ->path('/src\/Rest\/Resources?\//');

        $cases = [];

        foreach ($files as $file) {
            $source = $file->getContents();

            if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) || ! preg_match('/^class\s+(\w+)/m', $source, $class)) {
                continue;
            }

            $resourceClass = "{$namespace[1]}\\{$class[1]}";

            if (is_subclass_of($resourceClass, Resource::class)) {
                $cases[$class[1]] = [$resourceClass];
            }
        }

        ksort($cases);

        return $cases;
    }

    #[Test]
    #[DataProvider('restResources')]
    public function it_forbids_the_client_to_write_every_server_managed_field_the_resource_declares(string $resourceClass): void
    {
        $request = new RestRequest;
        $resource = app($resourceClass);
        $rules = $resource->rules($request);

        $unprotectedFields = array_filter(
            array_intersect(Resource::SERVER_MANAGED_FIELDS, $resource->fields($request)),
            fn (string $field): bool => ($rules[$field] ?? null) !== ['missing'],
        );

        $this->assertSame([], array_values($unprotectedFields), "{$resourceClass} leaves server-managed fields writable");
    }

    #[Test]
    public function it_discovers_every_resource_registered_on_the_rest_router(): void
    {
        $registeredResources = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): ?string => $route->getControllerClass())
            ->filter(fn (?string $controller): bool => $controller !== null && is_subclass_of($controller, RestController::class))
            ->unique()
            ->map(fn (string $controller): string => $controller::newResource()::class)
            ->values()
            ->all();

        $discoveredResources = array_column(self::restResources(), 0);

        $this->assertNotEmpty($registeredResources);
        $this->assertSame([], array_values(array_diff($registeredResources, $discoveredResources)));
    }
}

<?php

namespace Tests\Feature\Lomkit;

use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Http\Controllers\Controller as RestController;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Rest\Middleware\LimitMutateOperations;
use Tests\TestCase;

class MutateOperationsCapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function updateOperations(string $key, int $count): array
    {
        return array_fill(0, $count, [
            'operation' => 'update',
            'key' => $key,
            'attributes' => ['name' => 'Renamed'],
        ]);
    }

    private function sportActivityOf(User $user): SportActivity
    {
        $connection = IntegrationConnection::factory()->for($user)->create();

        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Original',
        ]);
    }

    #[Test]
    public function it_rejects_a_mutate_request_above_the_operation_cap(): void
    {
        $user = User::factory()->create();
        $activity = $this->sportActivityOf($user);

        $response = $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => $this->updateOperations($activity->id, 101),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
        $this->assertSame('Original', $activity->fresh()->name);
    }

    #[Test]
    public function it_applies_a_mutate_request_made_of_exactly_the_cap(): void
    {
        $user = User::factory()->create();
        $activity = $this->sportActivityOf($user);

        $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => $this->updateOperations($activity->id, 100),
        ])->assertOk();

        $this->assertSame('Renamed', $activity->fresh()->name);
    }

    #[Test]
    public function it_rejects_above_the_cap_on_a_resource_without_the_creation_guard(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => array_fill(0, 101, [
                'operation' => 'update',
                'key' => $task->id,
                'attributes' => ['title' => 'Renamed'],
            ]),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
    }

    #[Test]
    public function it_counts_the_operations_nested_inside_relations(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $task->id,
                'relations' => [
                    'reminders' => $this->updateOperations($task->id, 100),
                ],
            ]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
    }

    #[Test]
    public function it_reads_the_cap_from_the_configuration(): void
    {
        config(['osdd.rest.max_mutate_operations' => 2]);
        $user = User::factory()->create();
        $activity = $this->sportActivityOf($user);

        $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => $this->updateOperations($activity->id, 3),
        ])->assertUnprocessable();
    }

    #[Test]
    public function it_caps_the_mutate_route_of_every_rest_controller(): void
    {
        $mutateRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/') && str_ends_with($route->uri(), '/mutate'))
            ->filter(fn (LaravelRoute $route): bool => is_subclass_of((string) $route->getControllerClass(), RestController::class));

        $uncappedUris = $mutateRoutes
            ->reject(fn (LaravelRoute $route): bool => in_array(LimitMutateOperations::class, $route->gatherMiddleware(), true))
            ->map(fn (LaravelRoute $route): string => $route->uri())
            ->values()
            ->all();

        $this->assertGreaterThan(10, $mutateRoutes->count());
        $this->assertSame([], $uncappedUris);
    }
}

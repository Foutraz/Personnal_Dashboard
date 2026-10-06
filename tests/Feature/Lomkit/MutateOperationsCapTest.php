<?php

namespace Tests\Feature\Lomkit;

use Functional\Sport\Models\SportActivity;
use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
    }

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

    /**
     * @return list<string>
     */
    private function taskIdsOf(User $user, int $count): array
    {
        return Task::factory()->count($count)->create(['user_id' => $user->id])->modelKeys();
    }

    /**
     * @return list<string>
     */
    private function trashedTaskIdsOf(User $user, int $count): array
    {
        return Task::factory()->count($count)->trashed()->create(['user_id' => $user->id])->modelKeys();
    }

    /**
     * @return list<string>
     */
    private function unknownTaskIds(int $count): array
    {
        return array_map(fn (): string => (new Task)->newUniqueId(), range(1, $count));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function nestedMutationWithKeys(User $user, int $keyCount): array
    {
        $task = Task::factory()->create(['user_id' => $user->id]);

        return [
            'mutate' => [[
                'operation' => 'update',
                'key' => $task->id,
                'relations' => [
                    'reminders' => [
                        'operation' => 'attach',
                        'key' => $this->unknownTaskIds($keyCount),
                    ],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mutationWithKeysAndNestedOperations(int $keyCount, int $nestedCount): array
    {
        return [
            'mutate' => [[
                'operation' => 'update',
                'key' => $this->unknownTaskIds($keyCount),
                'relations' => [
                    'reminders' => array_fill(0, $nestedCount, [
                        'operation' => 'attach',
                        'key' => (new Task)->newUniqueId(),
                    ]),
                ],
            ]],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function deeplyNestedMutationWithKeys(int $depth, int $keyCount): array
    {
        $operation = ['operation' => 'attach', 'key' => $this->unknownTaskIds($keyCount)];

        for ($level = 0; $level < $depth; $level++) {
            $operation = [
                'operation' => 'update',
                'key' => $this->unknownTaskIds($keyCount),
                'relations' => ['reminders' => $operation],
            ];
        }

        return ['mutate' => [$operation]];
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
    public function it_counts_every_key_of_a_nested_mutation_against_the_cap(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', $this->nestedMutationWithKeys($user, 100));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate' => 'A mutate request cannot carry more than 100 operations.']);
    }

    #[Test]
    public function it_lets_a_nested_mutation_reach_the_cap_without_exceeding_it(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', $this->nestedMutationWithKeys($user, 99));

        $this->assertStringNotContainsString(
            'A mutate request cannot carry more than 100 operations.',
            $response->getContent(),
        );
    }

    #[Test]
    public function it_counts_an_operation_with_an_empty_key_at_least_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => array_fill(0, 101, [
                'operation' => 'create',
                'key' => [],
                'attributes' => [
                    'title' => 'Created',
                    'priority' => TaskPriority::Low->value,
                    'status' => TaskStatus::Pending->value,
                ],
            ]),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate' => 'A mutate request cannot carry more than 100 operations.']);
        $this->assertSame(0, Task::query()->count());
    }

    #[Test]
    public function it_multiplies_an_operation_by_its_keys_and_its_nested_operations(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', $this->mutationWithKeysAndNestedOperations(10, 10));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate' => 'A mutate request cannot carry more than 100 operations.']);
    }

    #[Test]
    public function it_rejects_a_nested_mutation_whose_operation_count_overflows_an_integer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', $this->deeplyNestedMutationWithKeys(20, 100));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate' => 'A mutate request cannot carry more than 100 operations.']);
    }

    #[Test]
    public function it_lets_a_multiplied_operation_reach_the_cap_without_exceeding_it(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', $this->mutationWithKeysAndNestedOperations(10, 9));

        $this->assertStringNotContainsString('A mutate request cannot carry more than 100 operations.', $response->getContent());
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

    #[Test]
    public function it_rejects_a_destroy_request_above_the_cap(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->taskIdsOf($user, 101);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/tasks', ['resources' => $taskIds]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('resources');
        $this->assertSame(101, Task::query()->count());
    }

    #[Test]
    public function it_destroys_exactly_the_cap_of_resources(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->taskIdsOf($user, 100);

        $this->actingAs($user, 'api')->deleteJson('/api/tasks', ['resources' => $taskIds])->assertOk();

        $this->assertSame(0, Task::query()->count());
        $this->assertSame(100, Task::onlyTrashed()->count());
    }

    #[Test]
    public function it_rejects_a_restore_request_above_the_cap(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->trashedTaskIdsOf($user, 101);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/restore', ['resources' => $taskIds]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('resources');
        $this->assertSame(101, Task::onlyTrashed()->count());
    }

    #[Test]
    public function it_rejects_a_force_delete_request_above_the_cap(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->trashedTaskIdsOf($user, 101);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/tasks/force', ['resources' => $taskIds]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('resources');
        $this->assertSame(101, Task::onlyTrashed()->count());
    }

    #[Test]
    public function it_rejects_nested_resource_ids_on_destroy(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->taskIdsOf($user, 2);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/tasks', ['resources' => [$taskIds]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['resources' => 'The resources field must be a list of identifiers.']);
        $this->assertSame(2, Task::query()->count());
    }

    #[Test]
    public function it_rejects_nested_resource_ids_on_restore(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->trashedTaskIdsOf($user, 2);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/restore', ['resources' => [$taskIds]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['resources' => 'The resources field must be a list of identifiers.']);
        $this->assertSame(2, Task::onlyTrashed()->count());
    }

    #[Test]
    public function it_rejects_nested_resource_ids_on_force_delete(): void
    {
        $user = User::factory()->create();
        $taskIds = $this->trashedTaskIdsOf($user, 2);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/tasks/force', ['resources' => [$taskIds]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['resources' => 'The resources field must be a list of identifiers.']);
        $this->assertSame(2, Task::onlyTrashed()->count());
    }

    #[Test]
    public function it_rejects_resource_ids_sent_as_an_object(): void
    {
        $user = User::factory()->create();
        [$firstTaskId, $secondTaskId] = $this->taskIdsOf($user, 2);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/tasks', [
            'resources' => ['first' => $firstTaskId, 'second' => $secondTaskId],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['resources' => 'The resources field must be a list of identifiers.']);
        $this->assertSame(2, Task::query()->count());
    }

    #[Test]
    public function it_caps_the_bulk_id_routes_of_every_rest_controller(): void
    {
        $bulkIdRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/'))
            ->filter(fn (LaravelRoute $route): bool => is_subclass_of((string) $route->getControllerClass(), RestController::class))
            ->filter(fn (LaravelRoute $route): bool => in_array($route->getActionMethod(), ['destroy', 'restore', 'forceDelete'], true));

        $uncappedRoutes = $bulkIdRoutes
            ->reject(fn (LaravelRoute $route): bool => in_array(LimitMutateOperations::class, $route->gatherMiddleware(), true))
            ->map(fn (LaravelRoute $route): string => "{$route->getActionMethod()} {$route->uri()}")
            ->values()
            ->all();

        $this->assertGreaterThan(10, $bulkIdRoutes->count());
        $this->assertSame([], $uncappedRoutes);
    }
}

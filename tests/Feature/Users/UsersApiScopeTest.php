<?php

namespace Tests\Feature\Users;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsersApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/users/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($user->name, $response->json('data.0.name'));
    }

    #[Test]
    public function it_forbids_updating_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Original']);

        $response = $this->actingAs($user, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $other->id, 'attributes' => ['name' => 'Hijacked']],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'name' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_user_to_update_themselves(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $this->actingAs($user, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $user->id, 'attributes' => ['name' => 'Updated']],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated',
        ]);
    }
}

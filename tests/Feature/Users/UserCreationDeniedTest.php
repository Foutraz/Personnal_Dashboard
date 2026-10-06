<?php

namespace Tests\Feature\Users;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserCreationDeniedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_denies_a_signed_in_user_the_creation_of_another_user(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->denies('create', User::class));
    }

    #[Test]
    public function it_still_lets_a_user_view_and_update_their_own_record(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $user));
        $this->assertTrue(Gate::forUser($user)->allows('update', $user));
    }

    #[Test]
    public function it_still_denies_viewing_or_updating_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->denies('view', $other));
        $this->assertTrue(Gate::forUser($user)->denies('update', $other));
    }

    #[Test]
    public function it_answers_a_validation_error_when_creating_a_user_through_the_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'create', 'attributes' => ['name' => 'Another']],
            ],
        ])->assertUnprocessable();

        $this->assertSame(1, User::query()->count());
    }
}

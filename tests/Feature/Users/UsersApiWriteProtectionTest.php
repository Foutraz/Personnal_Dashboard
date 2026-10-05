<?php

namespace Tests\Feature\Users;

use Functional\Users\Models\User;
use Functional\Users\Rest\Resource\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Rest\Http\Requests\RestRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsersApiWriteProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_changing_the_email_through_the_api(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this->actingAs($user, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $user->id, 'attributes' => ['email' => 'victim@example.com']],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mutate.0.attributes.email');
        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    #[Test]
    public function it_rejects_setting_the_id_through_the_api(): void
    {
        $user = User::factory()->create();
        $originalId = $user->id;

        $response = $this->actingAs($user, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $originalId, 'attributes' => ['id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mutate.0.attributes.id');
        $this->assertDatabaseHas('users', ['id' => $originalId]);
    }

    #[Test]
    public function it_declares_the_server_managed_and_unverifiable_fields_as_missing_on_every_mutation(): void
    {
        $rules = (new UserResource)->rules(app(RestRequest::class));

        $this->assertSame(['missing'], $rules['id']);
        $this->assertSame(['missing'], $rules['email']);
    }

    #[Test]
    public function it_declares_the_id_instead_of_a_ulid_field(): void
    {
        $fields = (new UserResource)->fields(app(RestRequest::class));

        $this->assertContains('id', $fields);
        $this->assertNotContains('ulid', $fields);
    }

    #[Test]
    public function it_filters_users_on_their_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/users/search', [
            'search' => [
                'filters' => [
                    ['field' => 'id', 'value' => $user->id],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame($user->id, $response->json('data.0.id'));
    }

    #[Test]
    public function it_sorts_users_on_their_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/users/search', [
            'search' => [
                'sorts' => [
                    ['field' => 'id', 'direction' => 'asc'],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame($user->id, $response->json('data.0.id'));
    }

    #[Test]
    public function it_answers_a_filter_on_the_removed_ulid_field_with_a_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/users/search', [
            'search' => [
                'filters' => [
                    ['field' => 'ulid', 'value' => $user->id],
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}

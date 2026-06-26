<?php

namespace Tests\Feature\Authentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionTablesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_publishes_the_spatie_permission_tables(): void
    {
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('role_has_permissions'));
    }

    #[Test]
    public function it_uses_a_string_morph_key_compatible_with_ulid_users(): void
    {
        $this->assertContains(Schema::getColumnType('model_has_permissions', 'model_id'), ['string', 'varchar']);
        $this->assertContains(Schema::getColumnType('model_has_roles', 'model_id'), ['string', 'varchar']);
    }

    #[Test]
    public function it_authorizes_an_api_search_without_a_server_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->postJson('/api/tasks/search', ['search' => []])
            ->assertOk();
    }
}

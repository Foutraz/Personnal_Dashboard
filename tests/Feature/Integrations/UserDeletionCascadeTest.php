<?php

namespace Tests\Feature\Integrations;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_user_connections_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        $user->delete();

        $this->assertSoftDeleted($connection);
    }

    #[Test]
    public function it_keeps_connections_of_other_users_untouched(): void
    {
        $deletedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        IntegrationConnection::factory()->for($deletedUser)->create();
        $otherConnection = IntegrationConnection::factory()->for($otherUser)->create();

        $deletedUser->delete();

        $this->assertNotSoftDeleted('integration_connections', ['id' => $otherConnection->id]);
    }
}

<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class ConnectionDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_activities_when_the_connection_is_deleted(): void
    {
        $connection = IntegrationConnection::factory()->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $connection->user_id,
            'integration_connection_id' => $connection->id,
        ]);

        $connection->delete();

        $this->assertSoftDeleted($activity);
    }

    #[Test]
    public function it_deletes_activities_through_the_user_deletion_chain(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
        ]);

        $user->delete();

        $this->assertSoftDeleted($connection);
        $this->assertSoftDeleted($activity);
    }
}

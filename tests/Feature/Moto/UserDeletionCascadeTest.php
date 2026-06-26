<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_moto_rides_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $ride = MotoRide::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertSoftDeleted($ride);
    }
}

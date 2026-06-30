<?php

namespace Tests\Feature\Notifications;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBellRendersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_notification_bell_on_the_authenticated_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSeeLivewire('notification-center');
    }
}

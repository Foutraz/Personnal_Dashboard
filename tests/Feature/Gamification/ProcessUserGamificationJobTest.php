<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessUserGamificationJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_awards_xp_across_all_domains_and_stays_idempotent(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'total_elevation_gain' => 100.0]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'completed_at' => now()->subDay()]);

        ProcessUserGamificationJob::dispatchSync($user->id);
        ProcessUserGamificationJob::dispatchSync($user->id);

        $this->assertSame(2, XpEntry::query()->where('user_id', $user->id)->count());
        $profile = PlayerProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame(21 + 3, $profile->total_xp);
    }

    #[Test]
    public function it_only_processes_sources_inside_the_since_window(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subMonths(3)]);
        SportActivity::factory()->create(['user_id' => $user->id, 'started_at' => now()->subDay()]);

        ProcessUserGamificationJob::dispatchSync($user->id, now()->subDays(7));

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_quietly_skips_a_deleted_user(): void
    {
        ProcessUserGamificationJob::dispatchSync('01hzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertSame(0, XpEntry::query()->count());
    }
}

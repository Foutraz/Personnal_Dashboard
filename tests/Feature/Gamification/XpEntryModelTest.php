<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XpEntryModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_an_xp_entry_through_its_factory(): void
    {
        $entry = XpEntry::factory()->create();

        $this->assertTrue(XpEntry::query()->whereKey($entry->id)->exists());
        $this->assertIsInt($entry->points);
        $this->assertNotNull($entry->occurred_at);
    }

    #[Test]
    public function it_ignores_a_duplicate_award_for_the_same_source(): void
    {
        $user = User::factory()->create();
        $entry = XpEntry::factory()->create(['user_id' => $user->id]);

        $duplicate = $entry->only(['user_id', 'domain', 'rule_key', 'source_type', 'source_id', 'points']);
        DB::table('xp_entries')->insertOrIgnore([
            ...$duplicate,
            'id' => strtolower((string) str()->ulid()),
            'domain' => $entry->domain->value,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, XpEntry::query()->where('user_id', $user->id)->count());
    }
}

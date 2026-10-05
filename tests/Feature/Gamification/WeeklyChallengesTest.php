<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyChallenges;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyChallengesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    #[Test]
    public function it_returns_the_challenges_of_the_user_for_the_week_ordered_by_domain_then_template(): void
    {
        $user = User::factory()->create();
        foreach ([ChallengeTemplateKey::ExplorationCells, ChallengeTemplateKey::SportMovingTime, ChallengeTemplateKey::MotoRideCount, ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::MotoDistance] as $template) {
            Challenge::factory()->for($user)->forTemplate($template)->create();
        }

        $templates = $this->service()->forWeek($user, $this->currentWeek())->map(fn (Challenge $challenge): ChallengeTemplateKey => $challenge->template_key)->all();

        $this->assertSame([
            ChallengeTemplateKey::SportDistance,
            ChallengeTemplateKey::SportMovingTime,
            ChallengeTemplateKey::MotoDistance,
            ChallengeTemplateKey::MotoRideCount,
            ChallengeTemplateKey::ExplorationCells,
        ], $templates);
    }

    #[Test]
    public function it_returns_a_zero_indexed_list(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::MotoDistance)->create();
        Challenge::factory()->for($user)->forTemplate(ChallengeTemplateKey::SportDistance)->create();

        $this->assertSame([0, 1], $this->service()->forWeek($user, $this->currentWeek())->keys()->all());
    }

    #[Test]
    public function it_leaves_out_the_challenges_of_other_users(): void
    {
        $user = User::factory()->create();
        $own = Challenge::factory()->for($user)->create();
        Challenge::factory()->create();

        $this->assertSame([$own->id], $this->service()->forWeek($user, $this->currentWeek())->pluck('id')->all());
    }

    #[Test]
    public function it_leaves_out_the_challenges_of_other_weeks(): void
    {
        $user = User::factory()->create();
        $current = Challenge::factory()->for($user)->create();
        $previous = Challenge::factory()->for($user)->forWeek($this->currentWeek()->previous())->create();

        $this->assertSame([$current->id], $this->service()->forWeek($user, $this->currentWeek())->pluck('id')->all());
        $this->assertSame([$previous->id], $this->service()->forWeek($user, $this->currentWeek()->previous())->pluck('id')->all());
    }

    #[Test]
    public function it_returns_an_empty_collection_when_the_week_has_no_challenge(): void
    {
        $this->assertTrue($this->service()->forWeek(User::factory()->create(), $this->currentWeek())->isEmpty());
    }

    private function service(): WeeklyChallenges
    {
        return $this->app->make(WeeklyChallenges::class);
    }

    private function currentWeek(): GamificationWeek
    {
        return $this->app->make(GamificationCalendar::class)->currentWeek();
    }
}

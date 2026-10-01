<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_xp_entries(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownEntries = XpEntry::factory()->count(2)->create(['user_id' => $user->id]);
        XpEntry::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($ownEntries->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_player_profile(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'level' => 7]);
        PlayerProfile::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/player-profiles/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(7, $response->json('data.0.level'));
    }

    #[Test]
    public function it_rejects_creating_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['points' => 5000],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, XpEntry::query()->count());
    }

    #[Test]
    public function it_rejects_updating_own_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();
        $entry = XpEntry::factory()->create(['user_id' => $user->id, 'points' => 10]);

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $entry->id,
                    'attributes' => ['points' => 65000],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(10, $entry->fresh()->points);
    }

    #[Test]
    public function it_rejects_deleting_own_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();
        $entry = XpEntry::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/xp-entries', [
            'resources' => [$entry->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(XpEntry::query()->whereKey($entry->id)->exists());
    }

    #[Test]
    public function it_rejects_updating_the_own_player_profile_through_the_api(): void
    {
        $user = User::factory()->create();
        $profile = PlayerProfile::factory()->create(['user_id' => $user->id, 'level' => 2]);

        $response = $this->actingAs($user, 'api')->postJson('/api/player-profiles/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $profile->id,
                    'attributes' => ['level' => 99],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $profile->fresh()->level);
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_streaks(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'current_count' => 4]);
        Streak::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(4, $response->json('data.0.current_count'));
    }

    #[Test]
    public function it_rejects_updating_own_streaks_through_the_api(): void
    {
        $user = User::factory()->create();
        $streak = Streak::factory()->create(['user_id' => $user->id, 'current_count' => 2]);

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $streak->id,
                    'attributes' => ['current_count' => 999],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $streak->fresh()->current_count);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/xp-entries/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/player-profiles/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/streaks/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/badges/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/badge-awards/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/challenges/search', ['search' => []])->assertUnauthorized();
    }

    #[Test]
    public function it_denies_creation_in_every_gamification_policy(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('create', XpEntry::class));
        $this->assertFalse($user->can('create', PlayerProfile::class));
        $this->assertFalse($user->can('create', Streak::class));
        $this->assertFalse($user->can('create', Badge::class));
        $this->assertFalse($user->can('create', BadgeAward::class));
        $this->assertFalse($user->can('create', Challenge::class));
    }

    #[Test]
    public function it_rejects_creating_streaks_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['domain' => 'sport', 'current_count' => 999, 'best_count' => 999],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Streak::query()->count());
    }

    #[Test]
    public function it_rejects_deleting_own_streaks_through_the_api(): void
    {
        $user = User::factory()->create();
        $streak = Streak::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/streaks', [
            'resources' => [$streak->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(Streak::query()->whereKey($streak->id)->exists());
    }

    #[Test]
    public function it_rejects_updating_another_users_streak_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = Streak::factory()->create(['current_count' => 2, 'best_count' => 5]);

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $foreign->id,
                    'attributes' => ['current_count' => 999],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $foreign->fresh()->current_count);
    }

    #[Test]
    public function it_ignores_deleting_another_users_streak_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = Streak::factory()->create();

        $response = $this->actingAs($user, 'api')->deleteJson('/api/streaks', [
            'resources' => [$foreign->id],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertTrue(Streak::query()->whereKey($foreign->id)->exists());
    }

    #[Test]
    public function it_lists_the_whole_badge_catalogue_for_any_authenticated_user(): void
    {
        $user = User::factory()->create();
        $badges = Badge::factory()->count(3)->create();
        BadgeAward::factory()->create(['badge_id' => $badges->first()->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($badges->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_exposes_the_catalogue_fields_of_a_badge(): void
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertSame($badge->key, $response->json('data.0.key'));
        $this->assertSame($badge->rule_key, $response->json('data.0.rule_key'));
        $this->assertSame($badge->domain->value, $response->json('data.0.domain'));
        $this->assertSame($badge->tier->value, $response->json('data.0.tier'));
        $this->assertSame($badge->xp_reward, $response->json('data.0.xp_reward'));
        $this->assertEquals($badge->threshold, $response->json('data.0.threshold'));
    }

    #[Test]
    public function it_refuses_including_the_awards_of_badges(): void
    {
        $user = User::factory()->create();
        $foreign = BadgeAward::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/search', [
            'search' => [
                'includes' => [['relation' => 'awards']],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.includes.0.relation');
        $this->assertStringNotContainsString($foreign->user_id, $response->getContent());
    }

    #[Test]
    public function it_refuses_filtering_badges_on_the_user_of_their_awards(): void
    {
        $user = User::factory()->create();
        $foreign = BadgeAward::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/search', [
            'search' => [
                'filters' => [['field' => 'awards.user_id', 'value' => $foreign->user_id]],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.filters.0.field');
        $this->assertStringNotContainsString($foreign->badge_id, $response->getContent());
    }

    #[Test]
    public function it_refuses_aggregating_the_awards_of_badges(): void
    {
        $user = User::factory()->create();
        BadgeAward::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/search', [
            'search' => [
                'aggregates' => [['relation' => 'awards', 'type' => 'count']],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.aggregates.0.relation');
    }

    #[Test]
    public function it_refuses_including_the_awards_of_the_badge_of_a_badge_award(): void
    {
        $user = User::factory()->create();
        $foreign = BadgeAward::factory()->create();
        BadgeAward::factory()->create(['user_id' => $user->id, 'badge_id' => $foreign->badge_id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [
                'includes' => [['relation' => 'badge.awards']],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.includes.0.relation');
        $this->assertStringNotContainsString($foreign->id, $response->getContent());
    }

    #[Test]
    public function it_rejects_creating_badges_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'key' => faker()->words(2),
                        'rule_key' => BadgeRuleKey::SportDistance->value,
                        'domain' => 'sport',
                        'tier' => 'gold',
                        'threshold' => faker()->number(1, 100),
                        'xp_reward' => faker()->number(1000, 9000),
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_rejects_updating_a_badge_through_the_api(): void
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();
        $originalReward = $badge->xp_reward;

        $response = $this->actingAs($user, 'api')->postJson('/api/badges/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $badge->id,
                    'attributes' => ['xp_reward' => $originalReward + faker()->number(1000, 9000)],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame($originalReward, $badge->fresh()->xp_reward);
    }

    #[Test]
    public function it_rejects_deleting_a_badge_through_the_api(): void
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();

        $response = $this->actingAs($user, 'api')->deleteJson('/api/badges', [
            'resources' => [$badge->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(Badge::query()->whereKey($badge->id)->exists());
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_badge_awards(): void
    {
        $user = User::factory()->create();
        $ownAwards = BadgeAward::factory()->count(2)->create(['user_id' => $user->id]);
        BadgeAward::factory()->count(3)->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($ownAwards->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_includes_the_badge_of_each_own_badge_award(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [
                'includes' => [['relation' => 'badge']],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($award->badge_id, $response->json('data.0.badge.id'));
        $this->assertSame($award->badge->key, $response->json('data.0.badge.key'));
    }

    #[Test]
    public function it_never_returns_another_users_award_when_including_the_badge(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $sharedBadge = Badge::factory()->create();
        $ownAward = BadgeAward::factory()->create(['user_id' => $user->id, 'badge_id' => $sharedBadge->id]);
        $foreignAward = BadgeAward::factory()->create(['user_id' => $other->id, 'badge_id' => $sharedBadge->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [
                'includes' => [['relation' => 'badge']],
            ],
        ]);

        $response->assertOk();
        $this->assertSame([$ownAward->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertStringNotContainsString($foreignAward->id, $response->getContent());
        $this->assertStringNotContainsString($other->id, $response->getContent());
    }

    #[Test]
    public function it_does_not_expose_the_user_of_a_badge_award(): void
    {
        $user = User::factory()->create();
        BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('user', $response->json('data.0'));
    }

    #[Test]
    public function it_refuses_including_the_user_relation_of_badge_awards(): void
    {
        $user = User::factory()->create();
        BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/search', [
            'search' => [
                'includes' => [['relation' => 'user']],
            ],
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_creating_badge_awards_through_the_api(): void
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['badge_id' => $badge->id, 'awarded_at' => now()->toDateTimeString()],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
        $this->assertSame(0, BadgeAward::query()->count());
    }

    #[Test]
    public function it_rejects_updating_own_badge_awards_through_the_api(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id, 'awarded_at' => now()->subDays(5)]);
        $originalAwardedAt = $award->awarded_at->toDateTimeString();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $award->id,
                    'attributes' => ['awarded_at' => now()->toDateTimeString()],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame($originalAwardedAt, $award->fresh()->awarded_at->toDateTimeString());
    }

    #[Test]
    public function it_prohibits_updating_the_badge_through_an_own_badge_award(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);
        $originalReward = $award->badge->xp_reward;

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $award->id,
                    'relations' => [
                        'badge' => [
                            'operation' => 'update',
                            'key' => $award->badge_id,
                            'attributes' => ['xp_reward' => $originalReward + faker()->number(1000, 9000)],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate.0.relations.badge');
        $this->assertSame($originalReward, $award->badge->fresh()->xp_reward);
    }

    #[Test]
    public function it_prohibits_attaching_another_badge_to_an_own_badge_award(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);
        $originalBadgeId = $award->badge_id;
        $otherBadge = Badge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $award->id,
                    'relations' => [
                        'badge' => ['operation' => 'attach', 'key' => $otherBadge->id],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate.0.relations.badge');
        $this->assertSame($originalBadgeId, $award->fresh()->badge_id);
    }

    #[Test]
    public function it_prohibits_detaching_the_badge_from_an_own_badge_award(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $award->id,
                    'relations' => [
                        'badge' => ['operation' => 'detach', 'key' => $award->badge_id],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate.0.relations.badge');
        $this->assertSame($award->badge_id, $award->fresh()->badge_id);
    }

    #[Test]
    public function it_prohibits_creating_a_badge_through_an_own_badge_award(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);
        $badgeCount = Badge::query()->count();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $award->id,
                    'relations' => [
                        'badge' => [
                            'operation' => 'create',
                            'attributes' => [
                                'key' => faker()->words(2),
                                'rule_key' => BadgeRuleKey::SportDistance->value,
                                'domain' => 'sport',
                                'tier' => 'gold',
                                'threshold' => faker()->number(1, 100),
                                'xp_reward' => faker()->number(1000, 9000),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate.0.relations.badge');
        $this->assertSame($badgeCount, Badge::query()->count());
    }

    #[Test]
    public function it_denies_attaching_and_detaching_a_badge_in_the_badge_award_policy(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);
        $otherBadge = Badge::factory()->create();

        $this->assertFalse($user->can('attachBadge', [$award, $otherBadge]));
        $this->assertFalse($user->can('detachBadge', [$award, $award->badge]));
    }

    #[Test]
    public function it_rejects_deleting_own_badge_awards_through_the_api(): void
    {
        $user = User::factory()->create();
        $award = BadgeAward::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/badge-awards', [
            'resources' => [$award->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(BadgeAward::query()->whereKey($award->id)->exists());
    }

    #[Test]
    public function it_rejects_updating_another_users_badge_award_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = BadgeAward::factory()->create(['awarded_at' => now()->subDays(5)]);
        $originalAwardedAt = $foreign->awarded_at->toDateTimeString();

        $response = $this->actingAs($user, 'api')->postJson('/api/badge-awards/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $foreign->id,
                    'attributes' => ['awarded_at' => now()->toDateTimeString()],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame($originalAwardedAt, $foreign->fresh()->awarded_at->toDateTimeString());
    }

    #[Test]
    public function it_ignores_deleting_another_users_badge_award_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = BadgeAward::factory()->create();

        $response = $this->actingAs($user, 'api')->deleteJson('/api/badge-awards', [
            'resources' => [$foreign->id],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertTrue(BadgeAward::query()->whereKey($foreign->id)->exists());
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_challenges(): void
    {
        $user = User::factory()->create();
        $ownChallenges = collect([
            Challenge::factory()->create(['user_id' => $user->id]),
            Challenge::factory()->forTemplate(ChallengeTemplateKey::SportActivityCount)->create(['user_id' => $user->id]),
        ]);
        Challenge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($ownChallenges->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_exposes_exactly_the_documented_fields_of_a_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $exposedFields = array_keys($response->json('data.0'));
        $documentedFields = [
            'id',
            'week_key',
            'template_key',
            'domain',
            'metric',
            'starts_at',
            'ends_at',
            'baseline_value',
            'target_value',
            'current_value',
            'xp_reward',
            'status',
            'accepted_at',
            'resolved_at',
            'created_at',
            'updated_at',
        ];
        sort($exposedFields);
        sort($documentedFields);
        $this->assertSame($documentedFields, $exposedFields);
        $this->assertSame($challenge->id, $response->json('data.0.id'));
        $this->assertSame($challenge->week_key, $response->json('data.0.week_key'));
        $this->assertSame($challenge->template_key->value, $response->json('data.0.template_key'));
        $this->assertSame($challenge->domain->value, $response->json('data.0.domain'));
        $this->assertSame($challenge->metric->value, $response->json('data.0.metric'));
        $this->assertSame(ChallengeStatus::Accepted->value, $response->json('data.0.status'));
        $this->assertSame($challenge->xp_reward, $response->json('data.0.xp_reward'));
    }

    #[Test]
    public function it_does_not_expose_the_user_of_a_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('user', $response->json('data.0'));
        $this->assertStringNotContainsString($user->id, $response->getContent());
    }

    #[Test]
    public function it_orders_challenges_by_the_latest_week_first(): void
    {
        $user = User::factory()->create();
        $currentWeek = Challenge::factory()->create(['user_id' => $user->id]);
        $previousWeek = Challenge::factory()->create([
            'user_id' => $user->id,
            'week_key' => '2026-W39',
            'starts_at' => $currentWeek->starts_at->copy()->subWeek(),
            'ends_at' => $currentWeek->ends_at->copy()->subWeek(),
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertSame([$currentWeek->id, $previousWeek->id], collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function it_refuses_filtering_challenges_on_their_user(): void
    {
        $user = User::factory()->create();
        $foreign = Challenge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [
                'filters' => [['field' => 'user_id', 'value' => $foreign->user_id]],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.filters.0.field');
        $this->assertStringNotContainsString($foreign->id, $response->getContent());
    }

    #[Test]
    public function it_refuses_including_the_user_relation_of_challenges(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/search', [
            'search' => [
                'includes' => [['relation' => 'user']],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('search.includes.0.relation');
    }

    #[Test]
    public function it_rejects_creating_challenges_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'week_key' => '2026-W40',
                        'template_key' => ChallengeTemplateKey::SportDistance->value,
                        'xp_reward' => faker()->number(1000, 9000),
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('mutate');
        $this->assertSame(0, Challenge::query()->count());
    }

    #[Test]
    public function it_rejects_updating_own_challenges_through_the_api(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->accepted()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $challenge->id,
                    'attributes' => ['status' => ChallengeStatus::Completed->value],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
    }

    #[Test]
    public function it_rejects_deleting_own_challenges_through_the_api(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/challenges', [
            'resources' => [$challenge->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(Challenge::query()->whereKey($challenge->id)->exists());
    }

    #[Test]
    public function it_rejects_updating_another_users_challenge_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = Challenge::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/challenges/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $foreign->id,
                    'attributes' => ['status' => ChallengeStatus::Completed->value],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(ChallengeStatus::Proposed, $foreign->fresh()->status);
    }

    #[Test]
    public function it_ignores_deleting_another_users_challenge_through_the_api(): void
    {
        $user = User::factory()->create();
        $foreign = Challenge::factory()->create();

        $response = $this->actingAs($user, 'api')->deleteJson('/api/challenges', [
            'resources' => [$foreign->id],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertTrue(Challenge::query()->whereKey($foreign->id)->exists());
    }
}

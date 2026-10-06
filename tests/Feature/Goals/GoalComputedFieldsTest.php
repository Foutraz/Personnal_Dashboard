<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalComputedFieldsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, int|string|null}>
     */
    public static function computedFieldValues(): array
    {
        $cases = [];

        foreach (['current_value' => 42, 'progress_percentage' => 100] as $field => $forgedValue) {
            $cases["{$field} filled"] = [$field, $forgedValue];
            $cases["{$field} null"] = [$field, null];
            $cases["{$field} empty string"] = [$field, ''];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('computedFieldValues')]
    public function it_rejects_the_owner_sending_a_computed_field_on_update(string $field, int|string|null $suppliedValue): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->manual()->create(['user_id' => $user->id, 'title' => 'Original']);
        $storedBefore = Goal::query()->sole()->getAttributes();

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $goal->id,
                'attributes' => ['title' => 'Renamed', $field => $suppliedValue],
            ]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame($storedBefore, Goal::query()->sole()->getAttributes());
    }

    #[Test]
    #[DataProvider('computedFieldValues')]
    public function it_rejects_the_owner_sending_a_computed_field_on_create(string $field, int|string|null $suppliedValue): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/goals/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Run 100km',
                    'type' => GoalType::Sport->value,
                    'metric' => GoalMetric::SportDistance->value,
                    'target_value' => 100,
                    'status' => GoalStatus::Active->value,
                    $field => $suppliedValue,
                ],
            ]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame(0, Goal::query()->count());
    }
}

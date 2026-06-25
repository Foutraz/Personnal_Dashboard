<?php

namespace Functional\Goals\Rest\Resource;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Models\Goal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class GoalResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Goal::class;

    /**
     * Disable policy authorization as per-user scoping is enforced by the control.
     */
    public function isAuthorizingEnabled(): bool
    {
        return false;
    }

    /**
     * The exposed fields that could be provided.
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'title',
            'description',
            'type',
            'metric',
            'target_value',
            'manual_current_value',
            'unit',
            'starts_at',
            'deadline',
            'status',
            'current_value',
            'progress_percentage',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * The validation rules shared by every mutate operation.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'title' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => [Rule::enum(GoalType::class)],
            'metric' => [Rule::enum(GoalMetric::class)],
            'target_value' => ['numeric', 'min:0'],
            'manual_current_value' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'starts_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'status' => [Rule::enum(GoalStatus::class)],
        ];
    }

    /**
     * The additional validation rules required when creating the resource.
     *
     * @return array<string, array<int, mixed>>
     */
    public function createRules(RestRequest $request): array
    {
        return [
            'title' => ['required'],
            'type' => ['required'],
            'metric' => ['required'],
            'target_value' => ['required'],
            'status' => ['required'],
        ];
    }

    /**
     * The exposed relations that could be provided.
     *
     * @return array<int, mixed>
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed scopes that could be provided.
     *
     * @return array<int, mixed>
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided.
     *
     * @return array<int, int>
     */
    public function limits(RestRequest $request): array
    {
        return [10, 25, 50, 100];
    }

    /**
     * The exposed default order applied to the resource.
     *
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['created_at' => 'desc'];
    }

    /**
     * The actions that should be linked.
     *
     * @return array<int, mixed>
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked.
     *
     * @return array<int, mixed>
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }
}

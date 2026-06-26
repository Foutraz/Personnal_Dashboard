<?php

namespace Functional\Todo\Rest\Resource;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class TaskResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Task::class;

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
            'priority',
            'status',
            'due_at',
            'completed_at',
            'position',
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
            'priority' => [Rule::enum(TaskPriority::class)],
            'status' => [Rule::enum(TaskStatus::class)],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'position' => ['integer', 'min:0'],
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
            'priority' => ['required'],
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
        return ['position' => 'asc'];
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

<?php

namespace Functional\RecurringExpenses\Rest\Resource;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class RecurringExpenseResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = RecurringExpense::class;

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
            'label',
            'amount',
            'currency',
            'category',
            'frequency',
            'due_day',
            'starts_at',
            'ends_at',
            'next_due_at',
            'active',
            'note',
            'created_at',
            'updated_at',
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
        return ['next_due_at' => 'asc'];
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

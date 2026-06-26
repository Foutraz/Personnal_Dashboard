<?php

namespace Functional\Finance\Rest\Resource;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class InvestmentTransactionResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = InvestmentTransaction::class;

    /**
     * The exposed fields that could be provided.
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'position_id',
            'type',
            'quantity',
            'unit_price',
            'executed_at',
            'note',
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
            'position_id' => ['string', Rule::exists('positions', 'id')->where('user_id', Auth::id())],
            'type' => [Rule::enum(TransactionType::class)],
            'quantity' => ['numeric', 'min:0'],
            'unit_price' => ['numeric', 'min:0'],
            'executed_at' => ['date'],
            'note' => ['nullable', 'string', 'max:1000'],
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
            'position_id' => ['required'],
            'type' => ['required'],
            'quantity' => ['required'],
            'unit_price' => ['required'],
            'executed_at' => ['required'],
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
        return ['executed_at' => 'desc'];
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

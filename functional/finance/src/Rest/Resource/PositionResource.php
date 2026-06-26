<?php

namespace Functional\Finance\Rest\Resource;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class PositionResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Position::class;

    /**
     * The exposed fields that could be provided.
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'asset_symbol',
            'asset_name',
            'asset_type',
            'quantity',
            'average_buy_price',
            'current_price',
            'currency',
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
            'asset_symbol' => ['string', 'max:255'],
            'asset_name' => ['string', 'max:255'],
            'asset_type' => [Rule::enum(AssetType::class)],
            'quantity' => ['numeric', 'min:0'],
            'average_buy_price' => ['numeric', 'min:0'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['string', 'size:3'],
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
            'asset_symbol' => ['required'],
            'asset_name' => ['required'],
            'asset_type' => ['required'],
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

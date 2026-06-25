<?php

namespace Functional\Users\Rest\Resource;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class UserResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = User::class;

    /**
     * The exposed fields that could be provided
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'ulid',
            'name',
            'email',
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
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255'],
        ];
    }

    /**
     * The exposed relations that could be provided
     *
     * @return array<int, mixed>
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed scopes that could be provided
     *
     * @return array<int, mixed>
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided
     *
     * @return array<int, int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 25, 50];
    }

    /**
     * The actions that should be linked
     *
     * @return array<int, mixed>
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked
     *
     * @return array<int, mixed>
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }
}

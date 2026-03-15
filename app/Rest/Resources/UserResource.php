<?php

namespace App\Rest\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

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
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'email',
        ];
    }

    /**
     * The exposed relations that could be provided
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed scopes that could be provided
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 25, 50,];
    }

    /**
     * The actions that should be linked
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }
}

<?php

namespace Functional\Sport\Rest\Resource;

use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Rest\Resources\Resource;

class SportActivityResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = SportActivity::class;

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
            'strava_id',
            'name',
            'sport_type',
            'distance',
            'moving_time',
            'elapsed_time',
            'total_elevation_gain',
            'average_speed',
            'max_speed',
            'average_heartrate',
            'max_heartrate',
            'kilojoules',
            'started_at',
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
            'strava_id' => ['integer', 'min:0'],
            'name' => ['string', 'max:255'],
            'sport_type' => [Rule::enum(SportType::class)],
            'distance' => ['numeric', 'min:0'],
            'moving_time' => ['integer', 'min:0'],
            'elapsed_time' => ['integer', 'min:0'],
            'total_elevation_gain' => ['numeric', 'min:0'],
            'average_speed' => ['nullable', 'numeric', 'min:0'],
            'max_speed' => ['nullable', 'numeric', 'min:0'],
            'average_heartrate' => ['nullable', 'numeric', 'min:0'],
            'max_heartrate' => ['nullable', 'numeric', 'min:0'],
            'kilojoules' => ['nullable', 'numeric', 'min:0'],
            'started_at' => ['date'],
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
            'strava_id' => ['required'],
            'name' => ['required'],
            'sport_type' => ['required'],
            'distance' => ['required'],
            'moving_time' => ['required'],
            'elapsed_time' => ['required'],
            'started_at' => ['required'],
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

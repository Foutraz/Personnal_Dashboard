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
            ...$this->serverManagedFieldRules($request),
            'strava_id' => ['missing'],
            'name' => ['string', 'max:255'],
            'sport_type' => [Rule::enum(SportType::class)],
            'distance' => ['missing'],
            'moving_time' => ['missing'],
            'elapsed_time' => ['missing'],
            'total_elevation_gain' => ['missing'],
            'average_speed' => ['missing'],
            'max_speed' => ['missing'],
            'average_heartrate' => ['missing'],
            'max_heartrate' => ['missing'],
            'kilojoules' => ['missing'],
            'started_at' => ['missing'],
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

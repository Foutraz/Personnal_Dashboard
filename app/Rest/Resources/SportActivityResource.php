<?php

namespace App\Rest\Resources;

use App\Enums\SportActivity\Type;
use App\Models\SportActivity;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;

class SportActivityResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<SportActivity>
     */
    public static $model = SportActivity::class;

    /**
     * @param RestRequest $request
     * @return string[]
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',

            'owner_id',
            'provider',
            'provider_id',
            'external_url',

            'type',
            'libelle',
            'description',

            'start_time',
            'end_time',
            'timezone',
            'duration',
            'moving_time',

            'distance',
            'average_speed',
            'max_speed',

            'elevation_gain',
            'elevation_loss',
            'max_altitude',
            'min_altitude',

            'start_latitude',
            'start_longitude',
            'end_latitude',
            'end_longitude',

            'weather_condition',
            'temperature',

            'average_heart_rate',
            'max_heart_rate',
            'min_heart_rate',

            'created_at',
            'updated_at',
        ];
    }

    /**
     * @param RestRequest $request
     * @return string[]
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('owner_id', UserResource::class),
        ];
    }

    /**
     * @param RestRequest $request
     * @return int[]
     */
    public function limits(RestRequest $request): array
    {
        return [10, 25, 50];
    }

    /**
     * Base validation rules.
     * @param RestRequest $request
     * @return array
     */
    public function rules(RestRequest $request): array
    {
        return [
            'id' => ['prohibited'],
            'owner_id' => ['prohibited'],

            'provider' => ['nullable', 'string', 'max:50'],
            'provider_id' => ['nullable', 'string', 'max:191'],
            'external_url' => ['nullable', 'url', 'max:2048'],

            'type' => ['nullable', Rule::enum(Type::class)],
            'libelle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'timezone' => ['nullable', 'string', 'max:64'],

            'duration' => ['nullable', 'integer', 'min:0'],
            'moving_time' => ['nullable', 'integer', 'min:0'],

            'distance' => ['nullable', 'numeric', 'min:0'],
            'average_speed' => ['nullable', 'numeric', 'min:0'],
            'max_speed' => ['nullable', 'numeric', 'min:0'],

            'elevation_gain' => ['nullable', 'integer'],
            'elevation_loss' => ['nullable', 'integer'],
            'max_altitude' => ['nullable', 'integer'],
            'min_altitude' => ['nullable', 'integer'],

            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'weather_condition' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric'],

            'average_heart_rate' => ['nullable', 'numeric', 'min:0'],
            'max_heart_rate' => ['nullable', 'numeric', 'min:0'],
            'min_heart_rate' => ['nullable', 'numeric', 'min:0'],

            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /**
     * Validation rules for creation.
     * @param RestRequest $request
     * @return array
     */
    public function createRules(RestRequest $request): array
    {
        return array_merge($this->rules($request), [
            'type' => ['required', Rule::enum(Type::class)],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
        ]);
    }
}

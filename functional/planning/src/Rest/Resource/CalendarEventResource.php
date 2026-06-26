<?php

namespace Functional\Planning\Rest\Resource;

use Functional\Planning\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Osdd\Rest\Resources\Resource;

class CalendarEventResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = CalendarEvent::class;

    /**
     * The exposed fields that could be provided.
     *
     * @return array<int, string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'provider',
            'external_id',
            'title',
            'description',
            'location',
            'starts_at',
            'ends_at',
            'all_day',
            'external_link',
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
            'provider' => [Rule::enum(IntegrationProvider::class)],
            'external_id' => ['string', 'max:255'],
            'title' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['date'],
            'ends_at' => ['nullable', 'date'],
            'all_day' => ['boolean'],
            'external_link' => ['nullable', 'string', 'max:2048'],
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

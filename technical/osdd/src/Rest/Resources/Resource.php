<?php

namespace Technical\Osdd\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource as RestResource;

abstract class Resource extends RestResource
{
    public const SERVER_MANAGED_FIELDS = ['id', 'created_at', 'updated_at'];

    /**
     * Enable policy authorization so the controls enforce per-user ownership.
     */
    public function isAuthorizingEnabled(): bool
    {
        return true;
    }

    /**
     * Build a "search" query for fetching resource.
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * Build a query for mutating resource.
     */
    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * Build a "destroy" query for the given resource.
     */
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * Build a "restore" query for the given resource.
     */
    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * Build a "forceDelete" query for the given resource.
     */
    public function forceDeleteQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(RestRequest $request): array
    {
        return $this->serverManagedFieldRules($request);
    }

    /**
     * Lomkit force-fills every declared field, so a client could choose or erase the key and the timestamps, and `prohibited` still lets a null or empty value through.
     *
     * @return array<string, list<string>>
     */
    protected function serverManagedFieldRules(RestRequest $request): array
    {
        $declaredFields = array_intersect(self::SERVER_MANAGED_FIELDS, $this->fields($request));

        return array_fill_keys($declaredFields, ['missing']);
    }
}

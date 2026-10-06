<?php

namespace Technical\Osdd\Rest\Resources\Concerns;

use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait ResolvesUndeclaredRelationPathsToNull
{
    /**
     * Resolve a relation path to null as soon as its first segment is not declared on this resource.
     */
    public function relation(string $name): ?Relation
    {
        $firstSegment = Str::before(relation_without_pivot($name), '.');

        $isDeclared = collect($this->getRelations(app(RestRequest::class)))
            ->contains(fn (Relation $relation): bool => $relation->relation === $firstSegment);

        if (! $isDeclared) {
            return null;
        }

        return parent::relation($name);
    }
}

<?php

namespace Technical\Osdd\Rest\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LimitMutateOperations
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxOperations = config()->integer('osdd.rest.max_mutate_operations');

        if ($this->countOperations((array) $request->input('mutate', [])) > $maxOperations) {
            throw ValidationException::withMessages([
                'mutate' => __('osdd::validation.mutate_operations_limit', ['max' => $maxOperations]),
            ]);
        }

        if ($request->has('resources') && ! $this->isListOfIdentifiers($request->input('resources'))) {
            throw ValidationException::withMessages([
                'resources' => __('osdd::validation.bulk_resources_shape'),
            ]);
        }

        if (count((array) $request->input('resources', [])) > $maxOperations) {
            throw ValidationException::withMessages([
                'resources' => __('osdd::validation.bulk_resources_limit', ['max' => $maxOperations]),
            ]);
        }

        return $next($request);
    }

    private function isListOfIdentifiers(mixed $resources): bool
    {
        return is_array($resources)
            && array_is_list($resources)
            && collect($resources)->every(fn (mixed $identifier): bool => is_string($identifier) || is_int($identifier));
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function countOperations(array $payload): int
    {
        $operations = is_string($payload['operation'] ?? null) ? $this->operationsPerKey($payload['key'] ?? null) : 0;

        foreach ($payload as $nested) {
            if (is_array($nested)) {
                $operations += $this->countOperations($nested);
            }
        }

        return $operations;
    }

    private function operationsPerKey(mixed $keys): int
    {
        return is_array($keys) ? count($keys) : 1;
    }
}

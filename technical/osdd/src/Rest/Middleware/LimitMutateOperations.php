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

        return $next($request);
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function countOperations(array $payload): int
    {
        $operations = is_string($payload['operation'] ?? null) ? 1 : 0;

        foreach ($payload as $nested) {
            if (is_array($nested)) {
                $operations += $this->countOperations($nested);
            }
        }

        return $operations;
    }
}

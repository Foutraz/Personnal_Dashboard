<?php

namespace Technical\Osdd\Rest\Controllers\Concerns;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Http\Requests\MutateRequest;

trait RejectsApiCreation
{
    /**
     * Reject create operations, at any nesting depth, before the mutation transaction begins.
     *
     * @throws ValidationException
     */
    protected function beforeMutate(MutateRequest $request): void
    {
        if ($this->containsCreateOperation((array) $request->input('mutate', []))) {
            throw ValidationException::withMessages([
                'mutate' => __('Cette ressource ne peut pas être créée via l\'API.'),
            ]);
        }
    }

    /**
     * Determine whether the mutation payload contains a create operation at any depth.
     *
     * @param  array<int|string, mixed>  $payload
     */
    private function containsCreateOperation(array $payload): bool
    {
        if (($payload['operation'] ?? null) === 'create') {
            return true;
        }

        foreach ($payload as $value) {
            if (is_array($value) && $this->containsCreateOperation($value)) {
                return true;
            }
        }

        return false;
    }
}

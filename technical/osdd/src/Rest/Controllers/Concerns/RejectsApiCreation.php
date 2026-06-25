<?php

namespace Technical\Osdd\Rest\Controllers\Concerns;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Http\Requests\MutateRequest;

trait RejectsApiCreation
{
    /**
     * Reject create operations before the mutation transaction begins.
     *
     * @throws ValidationException
     */
    protected function beforeMutate(MutateRequest $request): void
    {
        foreach ((array) $request->input('mutate', []) as $mutation) {
            if (($mutation['operation'] ?? null) === 'create') {
                throw ValidationException::withMessages([
                    'mutate' => __('Cette ressource ne peut pas être créée via l\'API.'),
                ]);
            }
        }
    }
}

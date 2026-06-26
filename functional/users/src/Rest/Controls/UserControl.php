<?php

namespace Functional\Users\Rest\Controls;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class UserControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model = User::class;

    /**
     * Restrict access to the authenticated user's own record.
     *
     * @return array<int, Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query->where($query->getModel()->getKeyName(), $user->getKey()))
                ->should(fn (Model $user, Model $model): bool => $model->getKey() === $user->getKey()),
        ];
    }
}

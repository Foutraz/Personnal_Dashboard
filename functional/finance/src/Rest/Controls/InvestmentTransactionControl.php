<?php

namespace Functional\Finance\Rest\Controls;

use Functional\Finance\Models\InvestmentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class InvestmentTransactionControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model = InvestmentTransaction::class;

    /**
     * Restrict access to the transactions owned by the authenticated user.
     *
     * @return array<int, Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query->where('user_id', $user->getKey()))
                ->should(fn (Model $user, Model $model): bool => $model->getAttribute('user_id') === $user->getKey()),
        ];
    }
}

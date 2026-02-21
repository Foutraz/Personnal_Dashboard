<?php

namespace App\Access\Controls;

use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\OwnPerimeter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class UserControl extends Control
{
     /**
      * The model the control refers to.
      * @var class-string<Model>
      */
     protected string $model = User::class;

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(function (User $user, string $method) {
                    return $user->can(sprintf('%s global users', $method));
                })
                ->should(function () {
                    return true;
                })
                ->query(function (Builder $query) {
                    return $query;
                }),
            OwnPerimeter::new()
            ->allowed(function (User $user, string $method) {
                return $user->can(sprintf('%s own users', $method));
            })
            ->should(function (User $user, User $model) {
                return (int) $model->id === (int) $user->id;
            })
            ->query(function (Builder $query, User $user) {
                return $query->where('id', $user->getKey());
            })
        ];
    }
}

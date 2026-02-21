<?php

namespace App\Access\Controls;

use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\OwnPerimeter;
use App\Models\SportActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class SportActivityControl extends Control
{
     /**
      * The model the control refers to.
      * @var class-string<Model>
      */
     protected string $model = SportActivity::class;

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
                    return $user->can(sprintf('%s global models', $method));
                })
                ->should(function () {
                    return true;
                })
                ->query(function (Builder $query) {
                    return $query;
                }),
            OwnPerimeter::new()
                ->allowed(function (User $user, string $method) {
                    return $user->can(sprintf('%s own models', $method));
                })
                ->should(function (User $user, SportActivity $sportActivity) {
                    return (int) $sportActivity->owner_id === (int) $user->id;
                })
                ->query(function (Builder $query, User $user) {
                    return $query->where('owner_id', $user->getKey());
                })
        ];
    }
}

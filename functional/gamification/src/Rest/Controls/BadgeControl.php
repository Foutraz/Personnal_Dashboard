<?php

namespace Functional\Gamification\Rest\Controls;

use Functional\Gamification\Models\Badge;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class BadgeControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model = Badge::class;

    /**
     * Open the whole badge catalogue to every authenticated user.
     *
     * @return array<int, Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => true),
        ];
    }
}

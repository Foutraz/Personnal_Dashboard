<?php

namespace Functional\Gamification\Rest\Controller;

use Functional\Gamification\Rest\Resource\BadgeAwardResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Concerns\RejectsApiCreation;
use Technical\Osdd\Rest\Controllers\Controller;

class BadgeAwardsController extends Controller
{
    use RejectsApiCreation;

    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = BadgeAwardResource::class;
}

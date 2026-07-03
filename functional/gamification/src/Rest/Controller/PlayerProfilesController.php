<?php

namespace Functional\Gamification\Rest\Controller;

use Functional\Gamification\Rest\Resource\PlayerProfileResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Concerns\RejectsApiCreation;
use Technical\Osdd\Rest\Controllers\Controller;

class PlayerProfilesController extends Controller
{
    use RejectsApiCreation;

    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = PlayerProfileResource::class;
}

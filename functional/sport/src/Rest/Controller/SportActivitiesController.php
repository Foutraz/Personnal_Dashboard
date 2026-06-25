<?php

namespace Functional\Sport\Rest\Controller;

use Functional\Sport\Rest\Resource\SportActivityResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Concerns\RejectsApiCreation;
use Technical\Osdd\Rest\Controllers\Controller;

class SportActivitiesController extends Controller
{
    use RejectsApiCreation;

    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = SportActivityResource::class;
}

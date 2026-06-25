<?php

namespace Functional\Goals\Rest\Controller;

use Functional\Goals\Rest\Resource\GoalResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class GoalsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = GoalResource::class;
}

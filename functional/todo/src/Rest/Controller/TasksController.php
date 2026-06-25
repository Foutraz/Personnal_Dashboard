<?php

namespace Functional\Todo\Rest\Controller;

use Functional\Todo\Rest\Resource\TaskResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class TasksController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = TaskResource::class;
}

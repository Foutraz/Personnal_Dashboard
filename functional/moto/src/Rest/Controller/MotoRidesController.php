<?php

namespace Functional\Moto\Rest\Controller;

use Functional\Moto\Rest\Resource\MotoRideResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class MotoRidesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = MotoRideResource::class;
}

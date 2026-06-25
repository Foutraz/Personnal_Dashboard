<?php

namespace Functional\Finance\Rest\Controller;

use Functional\Finance\Rest\Resource\PositionResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class PositionsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = PositionResource::class;
}

<?php

namespace Functional\Exploration\Rest\Controller;

use Functional\Exploration\Rest\Resource\ExploredCellResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class ExploredCellsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = ExploredCellResource::class;
}

<?php

namespace Functional\Gamification\Rest\Controller;

use Functional\Gamification\Rest\Resource\XpEntryResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Concerns\RejectsApiCreation;
use Technical\Osdd\Rest\Controllers\Controller;

class XpEntriesController extends Controller
{
    use RejectsApiCreation;

    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = XpEntryResource::class;
}

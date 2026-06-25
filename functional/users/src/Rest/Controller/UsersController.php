<?php

namespace Functional\Users\Rest\Controller;

use Functional\Users\Rest\Resource\UserResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class UsersController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = UserResource::class;
}

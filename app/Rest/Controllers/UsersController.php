<?php

namespace App\Rest\Controllers;

use App\Rest\Resources\UserResource;
use Lomkit\Rest\Http\Resource;

class UsersController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource = UserResource::class;
}

<?php

namespace App\Rest\Controllers;

use App\Rest\Resources\SportActivityResource;

class SportActivitiesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<SportActivityResource>
     */
    public static $resource = SportActivityResource::class;
}

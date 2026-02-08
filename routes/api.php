<?php

use App\Rest\Controllers\SportActivitiesController;
use App\Rest\Controllers\UsersController;
use Lomkit\Rest\Facades\Rest;

Rest::resource('users', UsersController::class);
Rest::resource('sportActivities', SportActivitiesController::class);

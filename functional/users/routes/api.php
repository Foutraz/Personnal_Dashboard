<?php

use Functional\Users\Rest\Controller\UsersController;
use Lomkit\Rest\Facades\Rest;

Rest::resource('users', UsersController::class);

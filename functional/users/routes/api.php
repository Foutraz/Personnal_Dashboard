<?php

use Functional\Users\Rest\Controller\UsersController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Rest::resource('/users', UsersController::class);
});

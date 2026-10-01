<?php

namespace Functional\Gamification\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ChallengeNotRespondableException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct(__('gamification::challenges.errors.not_respondable'));
    }
}

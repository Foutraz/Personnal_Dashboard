<?php

namespace Functional\Users\Events;

use Functional\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserDeleting
{
    use Dispatchable;

    public function __construct(public User $user) {}
}

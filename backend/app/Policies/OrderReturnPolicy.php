<?php

namespace Savv\Policies;

use Savv\Models\OrderReturn;
use Savv\Models\User;

class OrderReturnPolicy
{
    public function view(User $user, OrderReturn $return): bool
    {
        return $user->id === $return->order->user_id;
    }
}

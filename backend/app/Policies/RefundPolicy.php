<?php

namespace Savv\Policies;

use Savv\Models\Refund;
use Savv\Models\User;

class RefundPolicy
{
    public function view(User $user, Refund $refund): bool
    {
        return $user->id === $refund->order->user_id;
    }
}

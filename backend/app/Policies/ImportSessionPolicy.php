<?php

namespace Savv\Policies;

use Savv\Models\ImportSession;
use Savv\Models\User;

class ImportSessionPolicy
{
    public function view(User $user, ImportSession $importSession): bool
    {
        return $user->id === $importSession->user_id;
    }

    public function update(User $user, ImportSession $importSession): bool
    {
        return $user->id === $importSession->user_id;
    }
}

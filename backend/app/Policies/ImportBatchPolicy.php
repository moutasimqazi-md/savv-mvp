<?php

namespace Savv\Policies;

use Savv\Models\ImportBatch;
use Savv\Models\User;

class ImportBatchPolicy
{
    public function view(User $user, ImportBatch $importBatch): bool
    {
        return $user->id === $importBatch->user_id;
    }
}

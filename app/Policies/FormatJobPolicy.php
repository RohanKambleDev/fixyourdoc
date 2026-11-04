<?php

namespace App\Policies;

use App\Models\FormatJob;
use App\Models\User;

class FormatJobPolicy
{
    public function view(User $user, FormatJob $formatJob): bool
    {
        return $user->is($formatJob->user);
    }

    public function delete(User $user, FormatJob $formatJob): bool
    {
        return $user->is($formatJob->user);
    }
}

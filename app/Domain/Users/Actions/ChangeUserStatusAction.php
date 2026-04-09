<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Models\User;

class ChangeUserStatusAction
{
    public function execute(User $user, bool $status): User
    {
        $user->is_active = $status;
        $user->save();

        return $user;
    }
}

<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Models\User;

class ChangeUserRoleAction
{
    public function execute(User $user, string $role): User
    {
        $user->role = $role;
        $user->save();

        return $user;
    }
}

<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\DTO\UpdateUserDto;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    public function execute(User $user, UpdateUserDto $dto): User
    {
        if ($dto->name !== null) {
            $user->name = $dto->name;
        }

        if ($dto->email !== null) {
            $user->email = $dto->email;
        }

        if ($dto->password !== null) {
            $user->password = Hash::make($dto->password);
        }

        if ($dto->role !== null) {
            $user->role = $dto->role;
        }

        if ($dto->is_active !== null) {
            $user->is_active = $dto->is_active;
        }

        $user->save();

        return $user;
    }
}

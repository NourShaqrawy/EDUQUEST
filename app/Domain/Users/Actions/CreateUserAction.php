<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\DTO\CreateUserDto;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(CreateUserDto $dto): User
    {
        return User::create([
            'name'      => $dto->name,
            'email'     => $dto->email,
            'password'  => Hash::make($dto->password),
            'role'      => $dto->role,
            'is_active' => $dto->is_active,
        ]);
    }
}

<?php

namespace App\Domain\Users\DTO;

class CreateUserDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $role,
        public bool $is_active = true,
    ) {}
}

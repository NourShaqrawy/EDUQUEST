<?php

namespace App\Domain\Users\DTO;

class UpdateUserDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?string $role = null,
        public ?bool $is_active = null,
    ) {}
}

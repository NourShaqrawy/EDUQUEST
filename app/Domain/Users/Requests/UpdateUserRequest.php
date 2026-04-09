<?php

namespace App\Domain\Users\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Domain\Users\DTO\UpdateUserDto;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|max:255|unique:users,email,' . $this->id,
            'password'  => 'sometimes|string|min:6',
            'role'      => 'sometimes|in:admin,publisher,user',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function toDto(): UpdateUserDto
    {
        return new UpdateUserDto(
            name: $this->name ?? null,
            email: $this->email ?? null,
            password: $this->password ?? null,
            role: $this->role ?? null,
            is_active: $this->is_active ?? null,
        );
    }
}

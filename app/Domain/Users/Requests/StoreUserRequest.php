<?php

namespace App\Domain\Users\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Domain\Users\DTO\CreateUserDto;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // يمكنك لاحقاً إضافة صلاحيات
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:admin,publisher,user',
            'is_active' => 'boolean',
        ];
    }

    public function toDto(): CreateUserDto
    {
        return new CreateUserDto(
            name: $this->name,
            email: $this->email,
            password: $this->password,
            role: $this->role,
            is_active: $this->is_active ?? true,
        );
    }
}

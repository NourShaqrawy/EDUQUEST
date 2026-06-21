<?php

namespace App\Domain\Users\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'role'      => $this->role,
            'is_active' => $this->is_active,
            'theme'     => $this->theme ?? 'light',
            'language'  => $this->language ?? 'en',
            'created_at'=> $this->created_at?->toDateTimeString(),
            'updated_at'=> $this->updated_at?->toDateTimeString(),
        ];
    }
}

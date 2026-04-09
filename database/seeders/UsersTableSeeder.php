<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Publisher User',
            'email' => 'publisher@example.com',
            'role' => 'publisher',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'role' => 'user',
            'password' => Hash::make('password123'),
        ]);
    }
}

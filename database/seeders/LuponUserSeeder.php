<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LuponUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where(
            'slug',
            'lupon'
        )->firstOrFail();

        User::updateOrCreate(
            [
                'username' => 'lupon1',
            ],
            [
                'name' => 'Test Lupon Member',

                'email' =>
                    'lupon1@san-jose.local',

                'password' =>
                    Hash::make('SanJose@2026'),

                'role_id' =>
                    $role->id,

                'contact_number' =>
                    null,

                'is_active' =>
                    true,
            ]
        );
    }
}
<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where(
            'slug',
            'barangay_captain'
        )->firstOrFail();

        User::updateOrCreate(
            [
                'username' => 'captain',
            ],
            [
                'name' => 'Barangay Captain',
                'email' => 'captain@san-jose.local',
                'password' => Hash::make('SanJose@2026'),
                'role_id' => $role->id,
                'is_active' => true,
            ]
        );
    }
}
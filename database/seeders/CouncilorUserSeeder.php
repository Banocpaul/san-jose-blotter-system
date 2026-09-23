<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CouncilorUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where(
            'slug',
            'councilor'
        )->firstOrFail();

        User::updateOrCreate(
            [
                'username' => 'councilor1',
            ],
            [
                'name' => 'Test Councilor',
                'email' => 'councilor1@san-jose.local',
                'password' => Hash::make(
                    'SanJose@2026'
                ),
                'role_id' => $role->id,
                'is_active' => true,
            ]
        );
    }
}
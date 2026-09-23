<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (
            User::where(
                'username',
                'captain'
            )->exists()
        ) {
            return;
        }

        $password =
            env('ADMIN_INITIAL_PASSWORD');

        if (empty($password)) {
            throw new RuntimeException(
                'ADMIN_INITIAL_PASSWORD is not configured.'
            );
        }

        $role =
            Role::where(
                'slug',
                'barangay_captain'
            )->firstOrFail();

        User::create([
            'username' => 'captain',
            'name' => 'Barangay Captain',
            'email' => 'captain@san-jose.local',
            'password' => Hash::make($password),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
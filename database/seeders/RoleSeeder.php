<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Barangay Captain',
                'slug' => 'barangay_captain',
                'description' => 'Full system oversight and administration.',
            ],
            [
                'name' => 'Barangay Secretary',
                'slug' => 'secretary',
                'description' => 'Manages blotter records, residents, documents, and reports.',
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Assists with encoding and barangay operational records.',
            ],
            [
                'name' => 'Barangay Councilor',
                'slug' => 'councilor',
                'description' => 'Handles assigned cases and investigation activities.',
            ],
            [
                'name' => 'Lupon Member',
                'slug' => 'lupon',
                'description' => 'Handles mediation hearings and settlement proceedings.',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
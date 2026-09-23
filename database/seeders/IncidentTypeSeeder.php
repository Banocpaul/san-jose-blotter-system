<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $incidentTypes = [
            [
                'code' => 'PHY',
                'name' => 'Physical Altercation',
                'description' => 'Physical fights or confrontations between individuals.',
            ],
            [
                'code' => 'NOI',
                'name' => 'Noise Complaint',
                'description' => 'Complaints involving excessive or disruptive noise.',
            ],
            [
                'code' => 'PRO',
                'name' => 'Property Dispute',
                'description' => 'Disputes involving property, boundaries, or ownership.',
            ],
            [
                'code' => 'FAM',
                'name' => 'Family Dispute',
                'description' => 'Family-related disagreements requiring barangay intervention.',
            ],
            [
                'code' => 'THR',
                'name' => 'Threat or Harassment',
                'description' => 'Reported threats, intimidation, or harassment.',
            ],
            [
                'code' => 'THE',
                'name' => 'Theft',
                'description' => 'Reported loss or taking of personal property.',
            ],
            [
                'code' => 'VAN',
                'name' => 'Vandalism',
                'description' => 'Damage or defacement of property.',
            ],
            [
                'code' => 'DIS',
                'name' => 'Public Disturbance',
                'description' => 'Disturbances affecting public peace and order.',
            ],
            [
                'code' => 'FIN',
                'name' => 'Financial or Debt Dispute',
                'description' => 'Disputes involving money, loans, or unpaid obligations.',
            ],
            [
                'code' => 'OTH',
                'name' => 'Other',
                'description' => 'Other incidents not covered by the available categories.',
            ],
        ];

        foreach ($incidentTypes as $type) {
            DB::table('incident_types')->updateOrInsert(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
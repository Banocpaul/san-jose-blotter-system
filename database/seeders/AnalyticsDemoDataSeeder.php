<?php

namespace Database\Seeders;

use App\Enums\CaseStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\MediationOutcome;
use App\Models\MediationSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnalyticsDemoDataSeeder extends Seeder
{
    private const TOTAL_CASES = 1000;

    private const DEMO_MARKER =
        '[SYNTHETIC ANALYTICS DATA]';

    public function run(): void
    {
        $incidentTypes =
            IncidentType::where(
                'is_active',
                true
            )
                ->get();

        if ($incidentTypes->isEmpty()) {
            throw new RuntimeException(
                'No active incident types found. Run IncidentTypeSeeder first.'
            );
        }

        $creators =
            User::where(
                'is_active',
                true
            )
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->whereIn(
                            'slug',
                            [
                                'barangay_captain',
                                'secretary',
                                'staff',
                            ]
                        );
                    }
                )
                ->get();

        if ($creators->isEmpty()) {
            throw new RuntimeException(
                'No active Captain, Secretary, or Staff account found.'
            );
        }

        $councilors =
            User::where(
                'is_active',
                true
            )
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where(
                            'slug',
                            'councilor'
                        );
                    }
                )
                ->get();

        if ($councilors->isEmpty()) {
            throw new RuntimeException(
                'No active Councilor account found. Seed at least one Councilor first.'
            );
        }

        $luponMembers =
            User::where(
                'is_active',
                true
            )
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where(
                            'slug',
                            'lupon'
                        );
                    }
                )
                ->get();

        if ($luponMembers->isEmpty()) {
            throw new RuntimeException(
                'No active Lupon account found. Seed at least one Lupon member first.'
            );
        }

        $this->command?->info(
            'Generating '
            . self::TOTAL_CASES
            . ' synthetic analytics cases...'
        );

        $progress =
            $this->command?->getOutput()
                ->createProgressBar(
                    self::TOTAL_CASES
                );

        $progress?->start();

        for (
            $i = 1;
            $i <= self::TOTAL_CASES;
            $i++
        ) {
            DB::transaction(
                function () use (
                    $incidentTypes,
                    $creators,
                    $councilors,
                    $luponMembers
                ) {
                    $this->createSyntheticCase(
                        $incidentTypes->random(),
                        $creators->random(),
                        $councilors->random(),
                        $luponMembers->random()
                    );
                }
            );

            $progress?->advance();
        }

        $progress?->finish();

        $this->command?->newLine(2);

        $this->command?->info(
            'Synthetic analytics data created successfully.'
        );

        $this->command?->warn(
            'These records are DEMO DATA and are marked in the remarks field.'
        );
    }

    private function createSyntheticCase(
        IncidentType $incidentType,
        User $creator,
        User $councilor,
        User $lupon
    ): void {
        $status =
            $this->weightedStatus();

        $incidentAt =
            $this->randomIncidentDateTime();

        $reportedAt =
            $incidentAt
                ->copy()
                ->addMinutes(
                    random_int(
                        10,
                        720
                    )
                );

        if (
            $reportedAt->isFuture()
        ) {
            $reportedAt =
                now()->copy();
        }

        $closedAt =
            $this->closedAtFor(
                $status,
                $reportedAt
            );

        $sitio =
            $this->randomSitio();

        $street =
            $this->randomStreet();

        $case =
            BlotterCase::create([
                'incident_type_id' =>
                    $incidentType->id,

                'incident_date' =>
                    $incidentAt
                        ->toDateString(),

                'incident_time' =>
                    $incidentAt
                        ->format(
                            'H:i:s'
                        ),

                'location' =>
                    $street
                    . ', '
                    . $sitio
                    . ', Barangay San Jose',

                'narrative' =>
                    'Synthetic analytics record for a '
                    . strtolower(
                        $incidentType->name
                    )
                    . ' incident. Generated only for dashboard testing and business intelligence analysis.',

                'initial_action' =>
                    $this->randomInitialAction(),

                'remarks' =>
                    self::DEMO_MARKER
                    . ' Generated for BI dashboard testing.',

                'status' =>
                    $status,

                'created_by' =>
                    $creator->id,

                'reported_at' =>
                    $reportedAt,

                'closed_at' =>
                    $closedAt,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Complainant
        |--------------------------------------------------------------------------
        */

        $complainantSitio =
            random_int(
                1,
                100
            ) <= 88
                ? $sitio
                : $this->randomSitio();

        $complainant =
            $this->randomPerson();

        $case
            ->complainants()
            ->create([
                'resident_id' =>
                    null,

                'is_san_jose_resident' =>
                    true,

                'house_number' =>
                    (string) random_int(
                        1,
                        999
                    ),

                'sitio' =>
                    $complainantSitio,

                'first_name' =>
                    $complainant[
                        'first_name'
                    ],

                'middle_name' =>
                    $complainant[
                        'middle_name'
                    ],

                'last_name' =>
                    $complainant[
                        'last_name'
                    ],

                'suffix' =>
                    $complainant[
                        'suffix'
                    ],

                'contact_number' =>
                    $this->randomPhone(),

                'address' =>
                    'House No. '
                    . random_int(
                        1,
                        999
                    )
                    . ', '
                    . $complainantSitio
                    . ', Barangay San Jose',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Respondent
        |--------------------------------------------------------------------------
        */

        $respondent =
            $this->randomPerson();

        $respondentIsLocal =
            random_int(
                1,
                100
            ) <= 82;

        $respondentSitio =
            $respondentIsLocal
                ? $this->randomSitio()
                : null;

        $case
            ->respondents()
            ->create([
                'resident_id' =>
                    null,

                'is_san_jose_resident' =>
                    $respondentIsLocal,

                'house_number' =>
                    $respondentIsLocal
                        ? (string) random_int(
                            1,
                            999
                        )
                        : null,

                'sitio' =>
                    $respondentSitio,

                'first_name' =>
                    $respondent[
                        'first_name'
                    ],

                'middle_name' =>
                    $respondent[
                        'middle_name'
                    ],

                'last_name' =>
                    $respondent[
                        'last_name'
                    ],

                'suffix' =>
                    $respondent[
                        'suffix'
                    ],

                'contact_number' =>
                    $this->randomPhone(),

                'address' =>
                    $respondentIsLocal
                        ? (
                            'House No. '
                            . random_int(
                                1,
                                999
                            )
                            . ', '
                            . $respondentSitio
                            . ', Barangay San Jose'
                        )
                        : $this->randomOutsideAddress(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Councilor Assignment
        |--------------------------------------------------------------------------
        */

        if (
            $status !==
            CaseStatus::Pending->value
        ) {
            $assignedAt =
                $reportedAt
                    ->copy()
                    ->addHours(
                        random_int(
                            1,
                            24
                        )
                    );

            $assignmentCompletedAt =
                $status ===
                CaseStatus::UnderInvestigation->value
                    ? null
                    : $assignedAt
                        ->copy()
                        ->addDays(
                            random_int(
                                1,
                                5
                            )
                        );

            if (
                $closedAt
                &&
                $assignmentCompletedAt
                &&
                $assignmentCompletedAt
                    ->greaterThan(
                        $closedAt
                    )
            ) {
                $assignmentCompletedAt =
                    $closedAt
                        ->copy()
                        ->subHours(1);
            }

            $case
                ->assignments()
                ->create([
                    'assigned_to' =>
                        $councilor->id,

                    'assigned_by' =>
                        $creator->id,

                    'assignment_notes' =>
                        self::DEMO_MARKER
                        . ' Synthetic councilor assignment.',

                    'assigned_at' =>
                        $assignedAt,

                    'completed_at' =>
                        $assignmentCompletedAt,
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Mediation Data
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    CaseStatus::ForMediation->value,
                    CaseStatus::Settled->value,
                    CaseStatus::Referred->value,
                    CaseStatus::Dismissed->value,
                ],
                true
            )
        ) {
            $this->createMediationData(
                $case,
                $status,
                $creator,
                $lupon,
                $reportedAt,
                $closedAt
            );
        }
    }

    private function createMediationData(
        BlotterCase $case,
        string $caseStatus,
        User $creator,
        User $lupon,
        Carbon $reportedAt,
        ?Carbon $closedAt
    ): void {
        if (
            $caseStatus ===
            CaseStatus::ForMediation->value
        ) {
            $scheduledDate =
                now()
                    ->copy()
                    ->addDays(
                        random_int(
                            1,
                            21
                        )
                    );

            MediationSession::create([
                'blotter_case_id' =>
                    $case->id,

                'hearing_number' =>
                    1,

                'scheduled_date' =>
                    $scheduledDate
                        ->toDateString(),

                'scheduled_time' =>
                    sprintf(
                        '%02d:%02d:00',
                        random_int(
                            8,
                            16
                        ),
                        random_int(
                            0,
                            1
                        ) * 30
                    ),

                'venue' =>
                    'Barangay San Jose Hall',

                'lupon_member_id' =>
                    $lupon->id,

                'created_by' =>
                    $creator->id,

                'status' =>
                    'Scheduled',

                'mediation_notes' =>
                    self::DEMO_MARKER
                    . ' Synthetic scheduled hearing.',

                'completed_at' =>
                    null,
            ]);

            return;
        }

        $sessionDate =
            $reportedAt
                ->copy()
                ->addDays(
                    random_int(
                        2,
                        12
                    )
                );

        if (
            $closedAt
            &&
            $sessionDate
                ->greaterThanOrEqualTo(
                    $closedAt
                )
        ) {
            $sessionDate =
                $closedAt
                    ->copy()
                    ->subDay();
        }

        $session =
            MediationSession::create([
                'blotter_case_id' =>
                    $case->id,

                'hearing_number' =>
                    1,

                'scheduled_date' =>
                    $sessionDate
                        ->toDateString(),

                'scheduled_time' =>
                    sprintf(
                        '%02d:%02d:00',
                        random_int(
                            8,
                            16
                        ),
                        random_int(
                            0,
                            1
                        ) * 30
                    ),

                'venue' =>
                    'Barangay San Jose Hall',

                'lupon_member_id' =>
                    $lupon->id,

                'created_by' =>
                    $creator->id,

                'status' =>
                    'Completed',

                'mediation_notes' =>
                    self::DEMO_MARKER
                    . ' Synthetic completed mediation hearing.',

                'completed_at' =>
                    $closedAt
                    ?? $sessionDate
                        ->copy()
                        ->addHours(2),
            ]);

        $outcome =
            match ($caseStatus) {
                CaseStatus::Settled->value =>
                    'Settled',

                CaseStatus::Dismissed->value =>
                    'Dismissed',

                CaseStatus::Referred->value =>
                    random_int(
                        0,
                        1
                    ) === 1
                        ? 'Referred'
                        : 'No Agreement',

                default =>
                    'Referred',
            };

        MediationOutcome::create([
            'mediation_session_id' =>
                $session->id,

            'outcome' =>
                $outcome,

            'agreement_details' =>
                $outcome ===
                'Settled'
                    ? 'Synthetic settlement agreement created for analytics testing.'
                    : null,

            'referral_agency' =>
                in_array(
                    $outcome,
                    [
                        'Referred',
                        'No Agreement',
                    ],
                    true
                )
                    ? $this->randomReferralAgency()
                    : null,

            'remarks' =>
                self::DEMO_MARKER
                . ' Synthetic mediation outcome.',

            'recorded_by' =>
                $lupon->id,

            'recorded_at' =>
                $closedAt
                ?? $sessionDate
                    ->copy()
                    ->addHours(2),
        ]);
    }

    private function weightedStatus(): string
    {
        $roll =
            random_int(
                1,
                100
            );

        return match (true) {
            $roll <= 12 =>
                CaseStatus::Pending->value,

            $roll <= 30 =>
                CaseStatus::UnderInvestigation->value,

            $roll <= 45 =>
                CaseStatus::ForMediation->value,

            $roll <= 70 =>
                CaseStatus::Settled->value,

            $roll <= 85 =>
                CaseStatus::Resolved->value,

            $roll <= 95 =>
                CaseStatus::Referred->value,

            default =>
                CaseStatus::Dismissed->value,
        };
    }

    private function closedAtFor(
        string $status,
        Carbon $reportedAt
    ): ?Carbon {
        if (
            ! in_array(
                $status,
                [
                    CaseStatus::Settled->value,
                    CaseStatus::Resolved->value,
                    CaseStatus::Referred->value,
                    CaseStatus::Dismissed->value,
                ],
                true
            )
        ) {
            return null;
        }

        $closedAt =
            $reportedAt
                ->copy()
                ->addDays(
                    random_int(
                        1,
                        45
                    )
                )
                ->addHours(
                    random_int(
                        0,
                        12
                    )
                );

        if (
            $closedAt->isFuture()
        ) {
            $closedAt =
                now()->copy();
        }

        return $closedAt;
    }

    private function randomIncidentDateTime(): Carbon
    {
        $start =
            Carbon::create(
                2024,
                1,
                1,
                0,
                0,
                0
            );

        $end =
            now()->copy();

        $seconds =
            random_int(
                0,
                max(
                    1,
                    $start->diffInSeconds(
                        $end
                    )
                )
            );

        return $start
            ->copy()
            ->addSeconds(
                $seconds
            );
    }

    private function randomSitio(): string
    {
        return 'Sitio '
            . random_int(
                1,
                4
            );
    }

    private function randomStreet(): string
    {
        $streets = [
            'Rizal Street',
            'Mabini Street',
            'Bonifacio Street',
            'Del Pilar Street',
            'Luna Street',
            'Jacinto Street',
            'Quezon Street',
            'Sampaguita Street',
        ];

        return $streets[
            array_rand(
                $streets
            )
        ];
    }

    private function randomInitialAction(): string
    {
        $actions = [
            'Parties were advised to remain calm while the complaint was recorded.',
            'Initial facts were documented and the parties were informed of the barangay process.',
            'The complaint was received and endorsed for appropriate barangay action.',
            'Parties were advised regarding the next step in the blotter process.',
            'Basic incident details were verified for barangay record purposes.',
        ];

        return $actions[
            array_rand(
                $actions
            )
        ];
    }

    private function randomOutsideAddress(): string
    {
        $addresses = [
            'Mandaluyong City',
            'Pasig City',
            'Makati City',
            'Quezon City',
            'San Juan City',
        ];

        return $addresses[
            array_rand(
                $addresses
            )
        ];
    }

    private function randomReferralAgency(): string
    {
        $agencies = [
            'Philippine National Police',
            'City Legal Office',
            'Prosecutor Office',
            'Department of Social Welfare and Development',
        ];

        return $agencies[
            array_rand(
                $agencies
            )
        ];
    }

    private function randomPhone(): string
    {
        return '09'
            . str_pad(
                (string) random_int(
                    0,
                    999999999
                ),
                9,
                '0',
                STR_PAD_LEFT
            );
    }

    private function randomPerson(): array
    {
        $firstNames = [
            'Adrian',
            'Angela',
            'Carlo',
            'Camille',
            'Daniel',
            'Diana',
            'Edward',
            'Erika',
            'Francis',
            'Grace',
            'Joshua',
            'Julia',
            'Kevin',
            'Kristine',
            'Mark',
            'Maria',
            'Nathan',
            'Nicole',
            'Paolo',
            'Patricia',
            'Rafael',
            'Rose',
            'Samuel',
            'Sofia',
            'Vincent',
        ];

        $lastNames = [
            'Aquino',
            'Bautista',
            'Castillo',
            'Cruz',
            'Del Rosario',
            'Diaz',
            'Flores',
            'Garcia',
            'Gonzales',
            'Hernandez',
            'Lopez',
            'Mendoza',
            'Navarro',
            'Pascual',
            'Ramos',
            'Reyes',
            'Rivera',
            'Santos',
            'Torres',
            'Villanueva',
        ];

        $middleLetters = [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'J',
            'M',
            'R',
            'S',
        ];

        return [
            'first_name' =>
                $firstNames[
                    array_rand(
                        $firstNames
                    )
                ],

            'middle_name' =>
                $middleLetters[
                    array_rand(
                        $middleLetters
                    )
                ]
                . '.',

            'last_name' =>
                $lastNames[
                    array_rand(
                        $lastNames
                    )
                ],

            'suffix' =>
                random_int(
                    1,
                    100
                ) <= 5
                    ? 'Jr.'
                    : null,
        ];
    }
}

<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiveYearDemoDataSeeder extends Seeder
{
    private const MARKER = '[DEMO DATA 2022-2026]';

    public function run(): void
    {
        $incidentTypes = DB::table('incident_types')
            ->where('is_active', 1)
            ->pluck('id', 'code');

        $requiredCodes = [
            'PHY','NOI','PRO','FAM','THR',
            'THE','VAN','DIS','FIN','OTH',
        ];

        foreach ($requiredCodes as $code) {
            if (! isset($incidentTypes[$code])) {
                throw new RuntimeException(
                    "Missing incident type {$code}. Run IncidentTypeSeeder first."
                );
            }
        }

        $creatorId = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.is_active', 1)
            ->whereIn('r.slug', ['barangay_captain', 'secretary', 'staff'])
            ->orderBy('u.id')
            ->value('u.id');

        if (! $creatorId) {
            throw new RuntimeException(
                'No active Captain, Secretary, or Staff account found.'
            );
        }

        $councilorId = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.is_active', 1)
            ->where('r.slug', 'councilor')
            ->orderBy('u.id')
            ->value('u.id');

        $luponId = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.is_active', 1)
            ->where('r.slug', 'lupon')
            ->orderBy('u.id')
            ->value('u.id');

        $codes = [
            'PHY','NOI','PRO','FAM','THR',
            'THE','VAN','DIS','FIN','OTH',
            'PHY','NOI','PRO','FAM','THR',
            'THE','VAN','DIS','FIN','OTH',
        ];

        $statuses = [
            'Pending',
            'Under Investigation',
            'Under Investigation',
            'For Mediation',
            'For Mediation',
            'Settled',
            'Settled',
            'Settled',
            'Settled',
            'Settled',
            'Resolved',
            'Resolved',
            'Resolved',
            'Referred',
            'Referred',
            'Dismissed',
            'Pending',
            'Under Investigation',
            'For Mediation',
            'Settled',
        ];

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

        $dateTemplates = [
            '01-10','01-28','02-15','03-05','03-24',
            '04-12','05-01','05-20','06-08','06-27',
            '07-16','08-04','08-23','09-11','09-30',
            '10-19','11-07','11-26','12-10','12-22',
        ];

        $dateTemplates2026 = [
            '01-08','01-22','02-05','02-19','03-05',
            '03-19','04-02','04-16','04-30','05-14',
            '05-28','06-11','06-25','07-09','07-23',
            '08-06','08-20','09-03','09-12','09-20',
        ];

        $created = 0;
        $skipped = 0;

        foreach (range(2022, 2026) as $year) {
            $dates = $year === 2026
                ? $dateTemplates2026
                : $dateTemplates;

            foreach (range(1, 20) as $number) {
                $index = $number - 1;

                $reference = sprintf(
                    'DEMO-%d-%03d',
                    $year,
                    $number
                );

                if (
                    DB::table('blotter_cases')
                        ->where('reference_number', $reference)
                        ->exists()
                ) {
                    $skipped++;
                    continue;
                }

                $incidentAt = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $year . '-' . $dates[$index] . ' ' .
                    sprintf(
                        '%02d:%02d:00',
                        8 + ($index % 9),
                        $index % 2 === 0 ? 0 : 30
                    )
                );

                $reportedAt = $incidentAt
                    ->copy()
                    ->addMinutes(30 + (($index % 4) * 15));

                $status = $statuses[$index];

                $closedAt = null;

                if (
                    in_array(
                        $status,
                        ['Settled', 'Resolved', 'Referred', 'Dismissed'],
                        true
                    )
                ) {
                    $closedAt = $reportedAt
                        ->copy()
                        ->addDays(2 + ($index % 10))
                        ->addHours(2);
                }

                $sitio = 'Sitio ' . (($index % 4) + 1);
                $street = $streets[$index % count($streets)];
                $code = $codes[$index];

                DB::transaction(function () use (
                    $reference,
                    $incidentTypes,
                    $code,
                    $incidentAt,
                    $reportedAt,
                    $closedAt,
                    $status,
                    $creatorId,
                    $councilorId,
                    $luponId,
                    $sitio,
                    $street,
                    $year,
                    $number,
                    &$created
                ) {
                    $caseId = DB::table('blotter_cases')
                        ->insertGetId([
                            'reference_number' => $reference,
                            'incident_type_id' => $incidentTypes[$code],
                            'incident_date' => $incidentAt->toDateString(),
                            'incident_time' => $incidentAt->format('H:i:s'),
                            'location' => "{$street}, {$sitio}, Barangay San Jose",
                            'narrative' =>
                                'Synthetic demo blotter case created for dashboard and analytics testing.',
                            'initial_action' =>
                                'Initial facts were documented and the parties were informed of the barangay process.',
                            'remarks' =>
                                self::MARKER . ' Synthetic record only.',
                            'status' => $status,
                            'created_by' => $creatorId,
                            'reported_at' => $reportedAt,
                            'closed_at' => $closedAt,
                            'created_at' => $reportedAt,
                            'updated_at' => $reportedAt,
                            'deleted_at' => null,
                        ]);

                    DB::table('case_complainants')->insert([
                        'blotter_case_id' => $caseId,
                        'resident_id' => null,
                        'is_san_jose_resident' => 1,
                        'house_number' => (string) (100 + $number),
                        'sitio' => $sitio,
                        'first_name' => "Demo{$year}",
                        'middle_name' => 'A.',
                        'last_name' => sprintf('Complainant%03d', $number),
                        'suffix' => null,
                        'contact_number' =>
                            '09' . str_pad(
                                (string) (($year * 100) + $number),
                                9,
                                '0',
                                STR_PAD_LEFT
                            ),
                        'address' =>
                            "House No. " . (100 + $number) .
                            ", {$sitio}, Barangay San Jose",
                        'created_at' => $reportedAt,
                        'updated_at' => $reportedAt,
                    ]);

                    $respondentLocal = $number % 5 !== 0;

                    DB::table('case_respondents')->insert([
                        'blotter_case_id' => $caseId,
                        'resident_id' => null,
                        'is_san_jose_resident' =>
                            $respondentLocal ? 1 : 0,
                        'house_number' =>
                            $respondentLocal
                                ? (string) (200 + $number)
                                : null,
                        'sitio' =>
                            $respondentLocal
                                ? 'Sitio ' . (($number % 4) + 1)
                                : null,
                        'first_name' => "Demo{$year}",
                        'middle_name' => 'B.',
                        'last_name' => sprintf('Respondent%03d', $number),
                        'suffix' => null,
                        'contact_number' =>
                            '09' . str_pad(
                                (string) (500000 + ($year * 100) + $number),
                                9,
                                '0',
                                STR_PAD_LEFT
                            ),
                        'address' =>
                            $respondentLocal
                                ? "House No. " . (200 + $number) .
                                  ", Sitio " . (($number % 4) + 1) .
                                  ", Barangay San Jose"
                                : 'Mandaluyong City',
                        'created_at' => $reportedAt,
                        'updated_at' => $reportedAt,
                    ]);

                    if (
                        $councilorId &&
                        $status !== 'Pending'
                    ) {
                        $assignedAt = $reportedAt
                            ->copy()
                            ->addHours(4);

                        DB::table('case_assignments')->insert([
                            'blotter_case_id' => $caseId,
                            'assigned_to' => $councilorId,
                            'assigned_by' => $creatorId,
                            'assignment_notes' =>
                                self::MARKER . ' Demo councilor assignment.',
                            'assigned_at' => $assignedAt,
                            'completed_at' =>
                                $status === 'Under Investigation'
                                    ? null
                                    : (
                                        $closedAt
                                        ? $closedAt->copy()->subHour()
                                        : $assignedAt->copy()->addDays(3)
                                    ),
                            'created_at' => $reportedAt,
                            'updated_at' => $reportedAt,
                        ]);
                    }

                    if (
                        $luponId &&
                        in_array(
                            $status,
                            [
                                'For Mediation',
                                'Settled',
                                'Referred',
                                'Dismissed',
                            ],
                            true
                        )
                    ) {
                        if ($status === 'For Mediation') {
                            $scheduledAt = now()
                                ->copy()
                                ->addDays(($number % 20) + 1);
                        } else {
                            $scheduledAt = $reportedAt
                                ->copy()
                                ->addDays(3 + ($number % 5));
                        }

                        $sessionId = DB::table('mediation_sessions')
                            ->insertGetId([
                                'blotter_case_id' => $caseId,
                                'hearing_number' => 1,
                                'scheduled_date' =>
                                    $scheduledAt->toDateString(),
                                'scheduled_time' =>
                                    sprintf(
                                        '%02d:%02d:00',
                                        9 + ($number % 6),
                                        $number % 2 === 0 ? 0 : 30
                                    ),
                                'venue' =>
                                    'Barangay San Jose Hall',
                                'lupon_member_id' => $luponId,
                                'created_by' => $creatorId,
                                'status' =>
                                    $status === 'For Mediation'
                                        ? 'Scheduled'
                                        : 'Completed',
                                'mediation_notes' =>
                                    self::MARKER . ' Demo mediation session.',
                                'completed_at' =>
                                    $status === 'For Mediation'
                                        ? null
                                        : ($closedAt ?? $scheduledAt->copy()->addHours(2)),
                                'created_at' => $reportedAt,
                                'updated_at' => $reportedAt,
                            ]);

                        if ($status !== 'For Mediation') {
                            $outcome = match ($status) {
                                'Settled' => 'Settled',
                                'Dismissed' => 'Dismissed',
                                'Referred' =>
                                    $number % 2 === 0
                                        ? 'Referred'
                                        : 'No Agreement',
                                default => 'Referred',
                            };

                            DB::table('mediation_outcomes')->insert([
                                'mediation_session_id' => $sessionId,
                                'outcome' => $outcome,
                                'agreement_details' =>
                                    $outcome === 'Settled'
                                        ? 'Synthetic demo settlement agreement.'
                                        : null,
                                'referral_agency' =>
                                    in_array(
                                        $outcome,
                                        ['Referred', 'No Agreement'],
                                        true
                                    )
                                        ? 'City Legal Office'
                                        : null,
                                'remarks' =>
                                    self::MARKER . ' Demo mediation outcome.',
                                'recorded_by' => $luponId,
                                'recorded_at' =>
                                    $closedAt ?? $scheduledAt->copy()->addHours(2),
                                'created_at' => $reportedAt,
                                'updated_at' => $reportedAt,
                            ]);
                        }
                    }

                    $created++;
                });
            }
        }

        $this->command?->info(
            "Five-year demo seeding complete. Created: {$created}; skipped existing: {$skipped}."
        );

        $this->command?->info(
            'Expected total demo cases after a fresh run: 100 (20 per year, 2022-2026).'
        );
    }
}

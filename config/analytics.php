<?php

return [
    // Dates in Asia/Manila, YYYY-MM-DD. Maintain the barangay's applicable
    // national/local non-working holidays here; weekends are always excluded.
    'non_working_dates' => [],
    'sla_targets' => [
        'New' => ['days' => 1, 'unit' => 'working', 'start' => 'Complaint received'],
        'Under Assessment' => ['days' => 3, 'unit' => 'working', 'start' => 'Assessment starts'],
        'For Mediation' => ['days' => 15, 'unit' => 'calendar', 'start' => 'First mediation meeting'],
        'For Pangkat/Conciliation' => ['days' => 15, 'unit' => 'calendar', 'start' => 'Pangkat first convenes'],
        'For Further Action/CFA' => ['days' => 3, 'unit' => 'working', 'start' => 'Eligible for further action'],
    ],
];

<?php

return [
    // Defaults used when editable SLA settings are initialized. Subsequent
    // target/calendar changes belong in Settings → SLA Settings.
    // Non-working dates use Asia/Manila, YYYY-MM-DD.
    'non_working_dates' => [],
    'sla_targets' => [
        'New' => ['days' => 1, 'unit' => 'working', 'start' => 'Complaint received'],
        'Under Assessment' => ['days' => 3, 'unit' => 'working', 'start' => 'Assessment starts'],
        'For Mediation' => ['days' => 15, 'unit' => 'calendar', 'start' => 'First mediation meeting'],
        'For Pangkat/Conciliation' => ['days' => 15, 'unit' => 'calendar', 'start' => 'Pangkat first convenes'],
        'For Further Action/CFA' => ['days' => 3, 'unit' => 'working', 'start' => 'Eligible for further action'],
    ],
];

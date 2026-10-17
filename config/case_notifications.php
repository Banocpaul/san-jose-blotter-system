<?php

return [
    'enabled' => (bool) env('CASE_PARTY_NOTIFICATIONS_ENABLED', false),
    'mailer' => env('CASE_PARTY_NOTIFICATIONS_MAILER', 'smtp'),
    'queue' => 'notifications',
];

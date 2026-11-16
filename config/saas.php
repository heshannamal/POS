<?php

return [
    'default_trial_days' => max(1, (int) env('SAAS_TRIAL_DAYS', 14)),
    'default_currency' => env('SAAS_DEFAULT_CURRENCY', 'LKR'),
    'default_timezone' => env('SAAS_DEFAULT_TIMEZONE', 'Asia/Colombo'),
];

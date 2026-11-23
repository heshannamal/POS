<?php

return [
    // Turn on in production by default. Can temporarily disable to stage enrollment.
    'require_2fa' => (bool) env('PLATFORM_REQUIRE_2FA', env('APP_ENV', 'production') === 'production'),
    'verification_window_minutes' => 720,
    'password_min_length' => max(12, (int) env('SECURITY_PASSWORD_MIN_LENGTH', 12)),
    'password_expiry_days' => max(0, (int) env('SECURITY_PASSWORD_EXPIRY_DAYS', 0)),
    'login_max_failures' => 5,
    'login_lock_minutes' => 15,
    'session_idle_minutes' => 60,
    'alert_failure_count' => 5,
];

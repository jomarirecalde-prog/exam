<?php

return [
    'app_mode' => env('APP_MODE', 'online'),
    'sync_enabled' => env('SYNC_ENABLED', false),
    'sync_target_url' => env('SYNC_TARGET_URL'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'default_duration_minutes' => (int) env('EXAM_DEFAULT_DURATION', 60),
    'default_passing_percentage' => (float) env('EXAM_DEFAULT_PASSING_PERCENTAGE', 75),
    'session_timeout_minutes' => (int) env('SESSION_TIMEOUT_MINUTES', 120),
    'max_login_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('LOCKOUT_MINUTES', 15),
    'timer_warning_minutes' => [10, 5, 1],
    'policy_version' => env('EXAM_POLICY_VERSION', '1.0'),
    'max_violation_warnings' => (int) env('EXAM_MAX_VIOLATION_WARNINGS', 3),
    'violation_dedup_seconds' => (int) env('EXAM_VIOLATION_DEDUP_SECONDS', 3),
    'institution' => [
        'name' => env('INSTITUTION_NAME', 'Examination Management System'),
        'address' => env('INSTITUTION_ADDRESS', ''),
        'contact' => env('INSTITUTION_CONTACT', ''),
    ],
    'pwa' => [
        'name' => env('PWA_NAME', 'Online Examination System'),
        'short_name' => env('PWA_SHORT_NAME', 'Exam System'),
        'theme_color' => env('PWA_THEME_COLOR', '#142536'),
        'background_color' => env('PWA_BACKGROUND_COLOR', '#F6F7F9'),
    ],
    'offline_session_hours' => (int) env('OFFLINE_SESSION_HOURS', 168),
];

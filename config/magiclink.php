<?php

return [
    'expires_in_minutes' => (int) env('MAGIC_LINK_EXPIRES_IN_MINUTES', 15),
    'request_cooldown_seconds' => 60,
    'email_requests_per_window' => 3,
    'ip_requests_per_window' => 20,
    'verification_attempts_per_window' => 30,
    'window_seconds' => 900,
];
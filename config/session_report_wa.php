<?php

return [
    'enabled' => env('SESSION_REPORT_WA_ENABLED', false),
    'debounce_minutes' => (int) env('SESSION_REPORT_WA_DEBOUNCE_MINUTES', 10),
    'update_prefix' => env('SESSION_REPORT_WA_UPDATE_PREFIX', '[Update]'),
];

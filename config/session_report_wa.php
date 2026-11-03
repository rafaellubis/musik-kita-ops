<?php

return [
    'enabled' => env('SESSION_REPORT_WA_ENABLED', True),
    'debounce_minutes' => (int) env('SESSION_REPORT_WA_DEBOUNCE_MINUTES', 1),
    'update_prefix' => env('SESSION_REPORT_WA_UPDATE_PREFIX', '[Update]'),
];

<?php

return [
    'task_due_soon_days' => (int) env('NOTIFICATIONS_TASK_DUE_SOON_DAYS', 3),
    'meeting_starting_soon_minutes' => (int) env('NOTIFICATIONS_MEETING_STARTING_SOON_MINUTES', 60),
    'custody_expected_return_soon_days' => (int) env('NOTIFICATIONS_CUSTODY_EXPECTED_RETURN_SOON_DAYS', 3),
    'sync_fanout_max' => (int) env('NOTIFICATIONS_SYNC_FANOUT_MAX', 20),
    'bell_recent_limit' => (int) env('NOTIFICATIONS_BELL_RECENT_LIMIT', 10),
    'retention_days' => env('NOTIFICATIONS_RETENTION_DAYS'),
];

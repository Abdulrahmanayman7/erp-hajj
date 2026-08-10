<?php

return [
    'number_prefix' => env('TASK_NUMBER_PREFIX', 'TSK-'),
    'number_pad' => (int) env('TASK_NUMBER_PAD', 6),
];

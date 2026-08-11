<?php

return [
    'number_prefix' => env('DOCUMENT_NUMBER_PREFIX', 'DOC-'),
    'number_pad' => (int) env('DOCUMENT_NUMBER_PAD', 6),
    'disk' => env('DOCUMENT_STORAGE_DISK', 'local'),
    'max_size_bytes' => (int) env('DOCUMENT_MAX_SIZE_BYTES', 20 * 1024 * 1024),
    /**
     * Extension → allowed MIME types (server-detected MIME must be in this list).
     *
     * @var array<string, list<string>>
     */
    'mime_map' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'txt' => ['text/plain'],
    ],
];

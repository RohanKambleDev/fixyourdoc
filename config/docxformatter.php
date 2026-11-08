<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python interpreter
    |--------------------------------------------------------------------------
    |
    | Interpreter that has python-docx installed. Defaults to the project's own
    | virtual environment (see scripts/requirements.txt). Use an absolute path
    | so queue workers do not depend on their PATH.
    |
    */

    'python' => env('DOCX_FORMATTER_PYTHON', base_path('scripts/venv/bin/python')),

    'script' => base_path('scripts/docx_formatter.py'),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'timeout_seconds' => (int) env('DOCX_FORMATTER_TIMEOUT', 120),

    // Kept at or below Livewire's default temporary upload limit (12 MB).
    'max_upload_kb' => (int) env('DOCX_FORMATTER_MAX_UPLOAD_KB', 10240),

    // Placeholder allowance per account per hour, to be replaced by per-plan
    // limits once pricing is decided.
    'uploads_per_hour' => (int) env('DOCX_FORMATTER_UPLOADS_PER_HOUR', 10),

    // Guards against zip bombs: a .docx is a zip archive.
    'max_uncompressed_mb' => (int) env('DOCX_FORMATTER_MAX_UNCOMPRESSED_MB', 100),

    'disk' => env('DOCX_FORMATTER_DISK', 'local'),

];

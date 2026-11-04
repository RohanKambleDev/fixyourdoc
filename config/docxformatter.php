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

    'disk' => env('DOCX_FORMATTER_DISK', 'local'),

];

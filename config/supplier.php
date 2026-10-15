<?php
return [
    // Backend connection deliberately unavailable until approved and implemented.
    'flowtracker_integration_enabled' => false,
    'file_scan_command' => env('SUPPLIER_FILE_SCAN_COMMAND', 'clamscan'),
    'max_upload_kb' => 20480,
];

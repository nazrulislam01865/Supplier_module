<?php
return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => 30, // Supplier security: 30 minutes maximum inactivity.
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', '__Host-flowtrack_supplier_session'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => null,
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
];

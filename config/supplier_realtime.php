<?php

return [
    'key' => env('FLOWTRACK_REALTIME_KEY', ''),
    'host' => env('FLOWTRACK_REALTIME_HOST', ''),
    'port' => (int) env('FLOWTRACK_REALTIME_PORT', 443),
    'scheme' => env('FLOWTRACK_REALTIME_SCHEME', 'https'),
];

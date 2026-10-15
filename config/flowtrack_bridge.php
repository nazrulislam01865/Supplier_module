<?php

return [
    'enabled' => (bool) env('FLOWTRACK_BRIDGE_ENABLED', false),
    'secret' => (string) env('FLOWTRACK_SUPPLIER_BRIDGE_SECRET', ''),
    'buyer_url' => rtrim((string) env('FLOWTRACK_BUYER_URL', ''), '/'),
];

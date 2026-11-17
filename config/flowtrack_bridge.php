<?php

return [
    'enabled' => (bool) env('FLOWTRACK_BRIDGE_ENABLED', false),
    'secret' => (string) env('FLOWTRACK_SUPPLIER_BRIDGE_SECRET', ''),
    'product_api_key' => (string) env('FLOWTRACK_SUPPLIER_PRODUCT_API_KEY', ''),
    'product_module_url' => rtrim((string) env('FLOWTRACK_SUPPLIER_PRODUCT_MODULE_URL', env('FLOWTRACK_BUYER_URL', '')), '/'),
    'buyer_url' => rtrim((string) env('FLOWTRACK_BUYER_URL', ''), '/'),
];

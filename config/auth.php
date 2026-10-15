<?php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'supplier_users'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'supplier_users']],
    'providers' => ['supplier_users' => ['driver' => 'eloquent', 'model' => App\Models\SupplierUser::class]],
    'passwords' => ['supplier_users' => ['provider' => 'supplier_users', 'table' => 'password_reset_tokens', 'expire' => 30, 'throttle' => 60]],
    'password_timeout' => 3600,
];

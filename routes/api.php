<?php

use App\Http\Controllers\Integration\ReceiveFlowtrackOrder;
use App\Http\Controllers\Integration\ReceiveFlowtrackRfq;
use App\Http\Controllers\Integration\SyncFlowtrackSupplier;
use App\Http\Controllers\Integration\SyncFlowtrackBranding;
use App\Http\Controllers\Integration\ProvisionFlowtrackAccess;
use App\Http\Controllers\Integration\SendFlowtrackPasswordReset;
use Illuminate\Support\Facades\Route;

// Machine-to-machine endpoint: no session, cookies or browser CSRF tokens.
Route::post('/v1/integrations/flowtrack/orders', ReceiveFlowtrackOrder::class)
    ->middleware('throttle:60,1');

Route::post('/v1/integrations/flowtrack/suppliers', SyncFlowtrackSupplier::class)->middleware('throttle:60,1');
Route::post('/v1/integrations/flowtrack/branding', SyncFlowtrackBranding::class)->middleware('throttle:120,1');
Route::post('/v1/integrations/flowtrack/supplier-access', ProvisionFlowtrackAccess::class)->middleware('throttle:10,1');
Route::post('/v1/integrations/flowtrack/supplier-access/reset', SendFlowtrackPasswordReset::class)->middleware('throttle:10,1');

Route::post('/v1/integrations/flowtrack/rfqs', ReceiveFlowtrackRfq::class)->middleware('throttle:60,1');

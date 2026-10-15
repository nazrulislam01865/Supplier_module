<?php
use Illuminate\Support\Facades\Artisan;

\Illuminate\Support\Facades\Schedule::command('supplier:sync-quotations')->everyMinute()->withoutOverlapping();

// Repair network interruptions without polling from a supplier's browser.
\Illuminate\Support\Facades\Schedule::command('supplier:sync-price-proposals')->everyFiveMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('supplier:retry-product-images')->everyFifteenMinutes()->withoutOverlapping();

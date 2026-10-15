<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

final class SupplierInquiryTaskThrottleIsolationTest extends TestCase
{
    public function test_supplier_task_routes_have_independent_rate_limits(): void
    {
        foreach ([
            ['POST', '/api/v1/rfqs/87/supplier-task', 'throttle:supplier-inquiry-task'],
            ['GET', '/api/v1/rfqs/87/supplier-task/documents/901', 'throttle:supplier-inquiry-task-file'],
        ] as [$verb, $uri, $expected]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $verb));
            $middleware = $route->gatherMiddleware();
            $this->assertContains($expected, $middleware);
            $this->assertNotContains('throttle:60,1', $middleware);
            $this->assertNotContains('throttle:10,1', $middleware);
            $this->assertNotContains('throttle:30,1', $middleware);
        }
    }
}

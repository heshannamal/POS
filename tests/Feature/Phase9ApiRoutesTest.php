<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase9ApiRoutesTest extends TestCase
{
    public function test_api_routes_exist_with_device_auth_and_business_permission_checks(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.auth.login'));
        $expected=[
            'api.v1.tenants.me',
            'api.v1.tenants.devices.index',
            'api.v1.tenants.devices.revoke',
            'api.v1.tenants.auth.logout',
            'api.v1.tenants.branches',
            'api.v1.tenants.products',
            'api.v1.tenants.stock',
            'api.v1.tenants.registers',
            'api.v1.tenants.sales',
            'api.v1.tenants.sales.show',
            'api.v1.tenants.sync.stock',
            'api.v1.tenants.sales.store',
        ];
        foreach ($expected as $name) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, 'Missing '.$name);
            $middleware=$route->gatherMiddleware();
            $this->assertContains('auth:sanctum',$middleware,$name);
            $this->assertContains('api.device',$middleware,$name);
        }
        foreach(['api.v1.tenants.sales.store','api.v1.tenants.sync.stock','api.v1.tenants.products'] as $name){
            $this->assertContains('api.device:pos.sales',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),$name);
        }
    }

    public function test_sale_writes_require_active_subscription_and_non_get_method(): void
    {
        $route=Route::getRoutes()->getByName('api.v1.tenants.sales.store');
        $this->assertContains('pos.operational',$route->gatherMiddleware());
        $this->assertNotContains('GET',$route->methods());
    }
}

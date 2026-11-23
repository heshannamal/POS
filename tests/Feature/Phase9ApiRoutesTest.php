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
            'api.v1.tenants.sync.manifest',
            'api.v1.tenants.sales.store',
            'api.v1.tenants.shifts.index',
            'api.v1.tenants.shifts.open',
            'api.v1.tenants.shifts.movement',
            'api.v1.tenants.shifts.close',
        ];
        foreach ($expected as $name) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, 'Missing '.$name);
            $middleware=$route->gatherMiddleware();
            $this->assertContains('auth:sanctum',$middleware,$name);
            $this->assertContains('api.device',$middleware,$name);
        }
        foreach(['api.v1.tenants.shifts.index','api.v1.tenants.shifts.open',
            'api.v1.tenants.shifts.movement','api.v1.tenants.shifts.close'] as $name){
            $this->assertContains('api.device:pos.shifts.operate',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),$name);
        }
        foreach(['api.v1.tenants.sales.store','api.v1.tenants.sync.stock','api.v1.tenants.products'] as $name){
            $this->assertContains('api.device:pos.sales',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),$name);
        }
    }

    public function test_device_dashboard_routes_require_store_management_permissions(): void
    {
        foreach(['business.pos.devices.index','business.pos.devices.revoke'] as $name){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('tenant.member',$route->gatherMiddleware());
            $this->assertContains('tenant.permission:settings.manage',$route->gatherMiddleware());
        }
        $this->assertNotContains('GET',Route::getRoutes()->getByName('business.pos.devices.revoke')->methods());
    }

    public function test_shift_close_is_available_during_suspension_but_mutations_are_not(): void
    {
        foreach(['api.v1.tenants.shifts.open','api.v1.tenants.shifts.movement'] as $name) {
            $this->assertContains('pos.operational',Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
        $this->assertNotContains('pos.operational',
            Route::getRoutes()->getByName('api.v1.tenants.shifts.close')->gatherMiddleware());
    }

    public function test_sale_writes_require_active_subscription_and_non_get_method(): void
    {
        $route=Route::getRoutes()->getByName('api.v1.tenants.sales.store');
        $this->assertContains('pos.operational',$route->gatherMiddleware());
        $this->assertNotContains('GET',$route->methods());
    }
}

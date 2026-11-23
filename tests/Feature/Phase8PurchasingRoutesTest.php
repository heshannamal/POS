<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase8PurchasingRoutesTest extends TestCase
{
    public function test_purchasing_routes_are_tenant_and_permission_protected():void
    {
        $expected=[
            'business.purchasing.suppliers'=>'tenant.permission:pos.suppliers.manage',
            'business.purchasing.suppliers.store'=>'tenant.permission:pos.suppliers.manage',
            'business.purchasing.orders'=>'purchasing.view',
            'business.purchasing.orders.store'=>'tenant.permission:pos.purchases.manage',
            'business.purchasing.orders.approve'=>'tenant.permission:pos.purchases.manage',
            'business.purchasing.orders.cancel'=>'tenant.permission:pos.purchases.manage',
            'business.purchasing.orders.show'=>'purchasing.view',
            'business.purchasing.receipts.store'=>'tenant.permission:pos.receiving.manage',
            'business.purchasing.payments.store'=>'tenant.permission:pos.payables.manage',
            'business.purchasing.stock'=>'tenant.permission:pos.inventory.manage',
            'business.purchasing.stock.reorder'=>'tenant.permission:pos.inventory.manage',
            'business.purchasing.stock.count'=>'tenant.permission:pos.inventory.manage',
            'business.purchasing.reports'=>'tenant.permission:pos.purchasing.reports',
            'business.purchasing.reports.csv'=>'tenant.permission:pos.purchasing.reports',
        ];
        foreach($expected as $name=>$permission){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route,"Missing {$name}");
            $middlewares=$route->gatherMiddleware();
            $this->assertContains('tenant.member',$middlewares,$name);
            $this->assertContains($permission,$middlewares,$name);
        }
    }

    public function test_mutating_purchasing_endpoints_are_post_and_require_operational_tenant():void
    {
        foreach([
            'business.purchasing.suppliers.store',
            'business.purchasing.suppliers.update',
            'business.purchasing.suppliers.archive',
            'business.purchasing.orders.store',
            'business.purchasing.orders.approve',
            'business.purchasing.orders.cancel',
            'business.purchasing.receipts.store',
            'business.purchasing.payments.store',
            'business.purchasing.stock.reorder',
            'business.purchasing.stock.count',
        ] as $name){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertNotContains('GET',$route->methods(),$name);
            $this->assertContains('pos.operational',$route->gatherMiddleware(),$name);
        }
    }
}

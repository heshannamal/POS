<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase7PosRoutesTest extends TestCase
{
    public function test_cashier_and_financial_routes_exist_with_tenant_authorization(): void
    {
        $routes = [
            'business.pos.terminal'=>'pos.sales',
            'business.pos.checkout'=>'pos.sales',
            'business.pos.sales'=>'pos.sales',
            'business.pos.receipt'=>'pos.sales',
            'business.pos.products'=>'pos.inventory.manage',
            'business.pos.inventory'=>'pos.inventory.manage',
            'business.pos.inventory.adjust'=>'pos.inventory.manage',
            'business.pos.inventory.transfer'=>'pos.inventory.manage',
            'business.pos.registers'=>'pos.shifts.operate',
            'business.pos.shifts.open'=>'pos.shifts.operate',
            'business.pos.shifts.movement'=>'pos.shifts.operate',
            'business.pos.shifts.close'=>'pos.shifts.operate',
            'business.pos.returns'=>'pos.returns.manage',
            'business.pos.returns.create'=>'pos.returns.manage',
            'business.pos.returns.store'=>'pos.returns.manage',
            'business.pos.reports'=>'pos.reports.view',
            'business.pos.reports.csv'=>'pos.reports.view',
        ];

        foreach ($routes as $name=>$permission) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route: {$name}");
            $middleware=$route->gatherMiddleware();
            $this->assertContains('tenant.member',$middleware, "{$name} lacks tenant member protection");
            $this->assertContains('tenant.permission:'.$permission,$middleware, "{$name} lacks {$permission}");
        }
    }

    public function test_financial_mutation_routes_never_use_get(): void
    {
        foreach ([
            'business.pos.checkout','business.pos.shifts.open','business.pos.shifts.movement',
            'business.pos.shifts.close','business.pos.returns.store',
            'business.pos.inventory.transfer','business.pos.inventory.adjust',
        ] as $name) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertNotContains('GET',$route->methods(), "{$name} should not mutate through GET");
        }
    }
}

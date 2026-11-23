<?php

namespace Tests\Feature;

use App\Models\PosProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase9DeviceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $owner=User::factory()->create();
        $tenant=app(CreateTenant::class)->handle($owner,['name'=>'API Device Shop']);
        $branch=$tenant->branches->first();
        $register=DB::table('pos_registers')->insertGetId([
            'tenant_id'=>$tenant->id,'branch_id'=>$branch->id,'name'=>'Desk 1',
            'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('owner','tenant','branch','register');
    }

    private function deviceLogin(User $owner,Tenant $tenant,?string $uuid=null): array
    {
        $response=$this->postJson('/api/v1/auth/device-login',[
            'tenant_slug'=>$tenant->slug,'email'=>$owner->email,'password'=>'password',
            'device_uuid'=>$uuid??(string)Str::uuid(),
            'device_name'=>'Counter Terminal','platform'=>'desktop','app_version'=>'0.1.0',
        ])->assertCreated();
        return [
            'token'=>$response->json('data.access_token'),
            'id'=>$response->json('data.device_id'),
        ];
    }

    private function headers(string $token): array
    {
        return ['Authorization'=>'Bearer '.$token,'Accept'=>'application/json'];
    }

    public function test_device_login_and_revocation_prevent_reuse(): void
    {
        $f=$this->fixture();
        $device=$this->deviceLogin($f['owner'],$f['tenant']);
        $uri='/api/v1/tenants/'.$f['tenant']->slug;
        $this->withHeaders($this->headers($device['token']))
            ->getJson($uri.'/me')->assertOk()->assertJsonPath('data.user.id',$f['owner']->id);
        $this->withHeaders($this->headers($device['token']))
            ->deleteJson($uri.'/devices/'.$device['id'])->assertOk();
        $this->withHeaders($this->headers($device['token']))
            ->getJson($uri.'/me')->assertUnauthorized();
        $this->assertDatabaseHas('pos_api_devices',['id'=>$device['id'],'is_active'=>false]);
    }

    public function test_device_token_is_tenant_bound(): void
    {
        $f=$this->fixture();
        $otherOwner=User::factory()->create();
        $other=app(CreateTenant::class)->handle($otherOwner,['name'=>'Not Allowed Shop']);
        $device=$this->deviceLogin($f['owner'],$f['tenant']);
        $this->withHeaders($this->headers($device['token']))
            ->getJson('/api/v1/tenants/'.$other->slug.'/me')->assertForbidden();
    }

    public function test_same_shift_service_is_used_for_api_cashier_and_web_operations(): void
    {
        $f=$this->fixture(); $token=$this->deviceLogin($f['owner'],$f['tenant'])['token'];
        $uri='/api/v1/tenants/'.$f['tenant']->slug;
        $this->withHeaders($this->headers($token))->postJson($uri.'/shifts',[
            'register_id'=>$f['register'],'opening_cash'=>'100.00',
        ])->assertCreated();
        $shift=DB::table('pos_shifts')->where('tenant_id',$f['tenant']->id)->first();
        $this->assertNotNull($shift);
        $this->withHeaders($this->headers($token))->postJson($uri.'/shifts/'.$shift->id.'/cash-movements',[
            'type'=>'cash_in','amount'=>'25.00','reason'=>'Opening float',
        ])->assertCreated();
        $this->withHeaders($this->headers($token))->postJson($uri.'/shifts/'.$shift->id.'/close',[
            'closing_cash'=>'125.00','note'=>'Counted at close',
        ])->assertOk()->assertJsonPath('data.difference_minor',0);
        $this->assertDatabaseHas('pos_shifts',['id'=>$shift->id,'expected_cash_minor'=>12500,'closing_cash_minor'=>12500]);
        $this->withHeaders($this->headers($token))->postJson($uri.'/shifts/'.$shift->id.'/close',[
            'closing_cash'=>'125.00',
        ])->assertNotFound();
    }

    public function test_device_checkout_is_idempotent_and_cannot_oversell(): void
    {
        $f=$this->fixture(); $token=$this->deviceLogin($f['owner'],$f['tenant'])['token'];
        $uri='/api/v1/tenants/'.$f['tenant']->slug;
        $this->withHeaders($this->headers($token))->postJson($uri.'/shifts',[
            'register_id'=>$f['register'],'opening_cash'=>'20.00',
        ])->assertCreated();
        $shift=DB::table('pos_shifts')->where('tenant_id',$f['tenant']->id)->value('id');
        $product=PosProduct::create([
            'tenant_id'=>$f['tenant']->id,'name'=>'Test Item','sku'=>'SKU-TEST',
            'price_minor'=>500,'cost_minor'=>200,'track_stock'=>true,'is_active'=>true,
        ]);
        DB::table('pos_stocks')->insert([
            'tenant_id'=>$f['tenant']->id,'branch_id'=>$f['branch']->id,
            'product_id'=>$product->id,'quantity'=>5,'reorder_level'=>0,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $payload=[
            'branch_id'=>$f['branch']->id,'shift_id'=>$shift,'idempotency_key'=>(string)Str::uuid(),
            'items'=>[['product_id'=>$product->id,'quantity'=>2]],
            'payment_method'=>'cash',
        ];
        $this->withHeaders($this->headers($token))->postJson($uri.'/sales',$payload)->assertOk();
        $this->withHeaders($this->headers($token))->postJson($uri.'/sales',$payload)->assertOk();
        $this->assertSame(1,DB::table('pos_sales')->where('tenant_id',$f['tenant']->id)->count());
        $this->assertSame(3,(int)DB::table('pos_stocks')->where('product_id',$product->id)
            ->where('branch_id',$f['branch']->id)->value('quantity'));
        $payload['items'][0]['quantity']=1;
        $this->withHeaders($this->headers($token))->postJson($uri.'/sales',$payload)
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $payload['idempotency_key']=(string)Str::uuid();
        $payload['items'][0]['quantity']=4;
        $this->withHeaders($this->headers($token))->postJson($uri.'/sales',$payload)
            ->assertUnprocessable();
        $this->assertSame(3,(int)DB::table('pos_stocks')->where('product_id',$product->id)
            ->where('branch_id',$f['branch']->id)->value('quantity'));
    }

    public function test_owner_can_revoke_staff_device_from_web_dashboard(): void
    {
        $f=$this->fixture();$device=$this->deviceLogin($f['owner'],$f['tenant']);
        $this->actingAs($f['owner'])->get(route('business.pos.devices.index',$f['tenant']))
            ->assertOk()->assertSee('Counter Terminal');
        $this->actingAs($f['owner'])->delete(route('business.pos.devices.revoke',[$f['tenant'],$device['id']]))
            ->assertRedirect();
        $this->assertDatabaseHas('pos_api_devices',['id'=>$device['id'],'is_active'=>false]);
    }
    public function test_employee_cannot_access_unassigned_branch_or_open_shift_without_permission(): void
    {
        $f=$this->fixture();
        $staff=User::factory()->create();
        $f['tenant']->users()->attach($staff->id,['is_owner'=>false,'status'=>'active']);
        $cashierRole=DB::table('roles')->where('tenant_id',$f['tenant']->id)
            ->where('slug','cashier')->value('id');
        DB::table('role_user')->insert([
            'tenant_id'=>$f['tenant']->id,'role_id'=>$cashierRole,'user_id'=>$staff->id,
        ]);
        // Cashier role has pos.sales, but no branch assignment and no shift-operation permission.
        $token=$this->deviceLogin($staff,$f['tenant'])['token'];
        $uri='/api/v1/tenants/'.$f['tenant']->slug;
        $this->withHeaders($this->headers($token))
            ->getJson($uri.'/products?branch_id='.$f['branch']->id)->assertForbidden();
        $this->withHeaders($this->headers($token))
            ->postJson($uri.'/shifts',[
                'register_id'=>$f['register'],'opening_cash'=>'0.00',
            ])->assertForbidden();
    }

    public function test_platform_administrator_cannot_create_device_token(): void
    {
        $f=$this->fixture();
        $admin=User::factory()->create(['is_platform_admin'=>true,'platform_role'=>'super_admin']);
        $this->postJson('/api/v1/auth/device-login',[
            'tenant_slug'=>$f['tenant']->slug,'email'=>$admin->email,'password'=>'password',
            'device_uuid'=>(string)Str::uuid(),'device_name'=>'Admin device',
            'platform'=>'desktop',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('pos_api_devices',['user_id'=>$admin->id]);
    }

}

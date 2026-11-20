<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvancedTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin', bool $temporary = false): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => $role,
            'temporary_admin_expires_at' => $temporary ? now()->addDay() : null,
        ]);
    }

    private function tenant(string $name = 'Test Shop'): Tenant
    {
        return app(CreateTenant::class)->handle(
            User::factory()->create(), ['name' => $name]
        );
    }

    public function test_admin_creates_business_with_complete_profile_and_private_logo(): void
    {
        Storage::fake('local');
        $admin = $this->admin('operations');
        $owner = User::factory()->create(['email' => 'new-owner@example.test']);

        $this->actingAs($admin)->post(route('platform.tenants.store'), [
            'name' => 'Sri Lanka Store',
            'owner_email' => $owner->email,
            'contact_person' => 'Chief Contact',
            'contact_email' => 'office@store.test',
            'phone' => '+94 77 123 4567',
            'country' => 'lk',
            'website' => 'https://example.com',
            'registration_number' => 'BR-9000',
            'address' => 'Colombo, Sri Lanka',
            'logo' => UploadedFile::fake()->image('shop.png'),
        ])->assertRedirect();

        $tenant = Tenant::where('name', 'Sri Lanka Store')->firstOrFail();
        $this->assertSame('LK', $tenant->country);
        $this->assertSame('Chief Contact', $tenant->contact_person);
        $this->assertSame('https://example.com', $tenant->website);
        $this->assertSame('BR-9000', $tenant->registration_number);
        $this->assertSame('completed', $tenant->onboarding_status);
        $this->assertSame(100, $tenant->onboarding_progress);
        $this->assertTrue($owner->isOwnerOf($tenant));
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('local')->assertExists($tenant->logo_path);
        $this->actingAs($admin)->get(route('platform.tenants.logo', $tenant))->assertOk();
        $this->actingAs($owner)->get(route('business.logo.show', $tenant))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'tenant.logo.updated']);
    }

    public function test_operations_can_update_profile_and_logo_without_changing_tenant_slug(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $admin = $this->admin('operations');
        $slug = $tenant->slug;

        $this->actingAs($admin)->put(route('platform.tenants.profile', $tenant), [
            'name' => 'Renamed Shop', 'contact_email' => 'shop@example.com',
            'contact_person' => 'Manager One', 'phone' => '0712345678',
            'address' => 'North Street', 'country' => 'us',
            'website' => 'https://pos.example.com', 'registration_number' => 'REG-1234',
            'currency' => 'LKR', 'timezone' => 'Asia/Colombo',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('Renamed Shop', $tenant->name);
        $this->assertSame('US', $tenant->country);
        $this->assertSame('Manager One', $tenant->contact_person);
        $this->assertSame($slug, $tenant->slug);

        $this->actingAs($admin)->post(route('platform.tenants.logo.upload',$tenant), [
            'logo' => UploadedFile::fake()->image('brand.png'),
        ])->assertRedirect();

        $first = $tenant->fresh()->logo_path;
        Storage::disk('local')->assertExists($first);

        $this->actingAs($admin)->post(route('platform.tenants.logo.upload',$tenant), [
            'logo' => UploadedFile::fake()->image('newbrand.png'),
        ])->assertRedirect();
        $second = $tenant->fresh()->logo_path;
        $this->assertNotSame($first,$second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($admin)->delete(route('platform.tenants.logo.delete',$tenant))
            ->assertRedirect();
        $this->assertNull($tenant->fresh()->logo_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_business_list_filters_by_owner_plan_country_status_and_dates(): void
    {
        $admin = $this->admin('operations');
        $firstOwner = User::factory()->create(['email' => 'first-owner@example.test']);
        $first = app(CreateTenant::class)->handle($firstOwner, ['name' => 'Lanka Boutique', 'country' => 'LK']);
        $second = $this->tenant('US Retail');
        $second->update(['country' => 'US']);
        $second->update(['status' => 'suspended']);
        $second->subscription()->update(['status' => 'paused']);

        $businessPlan = Plan::where('slug', 'business')->firstOrFail();
        $first->subscription()->update(['plan_id' => $businessPlan->id]);

        $this->actingAs($admin)->get(route('platform.tenants.index', [
            'search' => 'first-owner@example.test',
            'country' => 'LK',
            'plan' => 'business',
            'status' => 'trial',
            'from' => now()->subDay()->format('Y-m-d'),
            'to' => now()->addDay()->format('Y-m-d'),
            'sort' => 'name', 'direction' => 'asc', 'per_page' => 25,
        ]))->assertOk()->assertSee('Lanka Boutique')->assertDontSee('US Retail');

        $this->actingAs($admin)->get(route('platform.tenants.index', [
            'country' => 'US', 'status' => 'suspended',
        ]))->assertOk()->assertSee('US Retail')->assertDontSee('Lanka Boutique');

        $this->actingAs($admin)->get(route('platform.tenants.index', [
            'sort' => 'id;DROP TABLE tenants',
        ]))->assertSessionHasErrors('sort');
    }

    public function test_notes_are_private_tenant_scoped_and_audited_without_note_text_in_logs(): void
    {
        $admin = $this->admin('operations');
        $support = $this->admin('support');
        $first = $this->tenant('Private Shop');
        $second = $this->tenant('Other Shop');

        $this->actingAs($admin)->post(route('platform.tenants.notes.store',$first), [
            'body' => 'Internal secret account decision.',
        ])->assertRedirect();
        $note = $first->notes()->firstOrFail();
        $this->actingAs($admin)->get(route('platform.tenants.show',$first))
            ->assertOk()->assertSee('Internal secret account decision.');

        $this->actingAs($support)->get(route('platform.tenants.show',$first))
            ->assertOk()->assertDontSee('Internal secret account decision.');
        $this->actingAs($support)->post(route('platform.tenants.notes.store',$first), [
            'body' => 'Unauthorized',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('platform.tenants.notes.destroy',[$second,$note]), [
            'reason' => 'Wrong tenant',
        ])->assertNotFound();
        $this->assertDatabaseHas('tenant_notes',['id' => $note->id]);

        $this->actingAs($admin)->delete(route('platform.tenants.notes.destroy',[$first,$note]), [
            'reason' => 'No longer required',
        ])->assertRedirect();
        $this->assertDatabaseMissing('tenant_notes',['id' => $note->id]);

        $audit = $first->id;
        $this->assertDatabaseHas('audit_logs',['tenant_id' => $audit,'action' => 'tenant.note.created']);
        $this->assertDatabaseHas('audit_logs',['tenant_id' => $audit,'action' => 'tenant.note.deleted']);
        $this->assertStringNotContainsString('Internal secret account decision.',
            (string) \App\Models\AuditLog::where('action','tenant.note.created')->value('metadata'));
    }

    public function test_onboarding_progress_is_managed_without_deleting_the_workspace(): void
    {
        $admin = $this->admin('operations');
        $tenant = $this->tenant('New Business');
        $this->assertSame(100,$tenant->onboarding_progress);

        $this->actingAs($admin)->patch(route('platform.tenants.onboarding.update',$tenant),[
            'onboarding_status' => 'pending',
            'reason' => 'Waiting for registration documents.',
        ])->assertRedirect();

        $this->assertSame('pending',$tenant->fresh()->onboarding_status);
        $this->assertNull($tenant->fresh()->onboarding_completed_at);
        $this->assertSame(25,$tenant->fresh()->onboarding_progress);

        $this->actingAs($admin)->patch(route('platform.tenants.onboarding.update',$tenant),[
            'onboarding_status' => 'completed',
            'reason' => 'Documents verified and approved.',
        ])->assertRedirect();

        $this->assertNotNull($tenant->fresh()->onboarding_completed_at);
        $this->assertDatabaseHas('audit_logs',[
            'tenant_id' => $tenant->id,'action' => 'tenant.onboarding.changed',
        ]);
    }

    public function test_deactivate_and_restore_preserve_paid_subscription_source_and_dates(): void
    {
        $admin = $this->admin('operations');
        $tenant = $this->tenant('Paid Business');
        $tenant->update(['status' => 'active']);
        $tenant->subscription()->update([
            'status' => 'active', 'source' => 'asset_review',
            'ends_at' => now()->addMonth(),
        ]);
        $originalEnd = $tenant->fresh()->subscription->ends_at->toDateTimeString();

        $this->actingAs($admin)->patch(route('platform.tenants.lifecycle.update',$tenant),[
            'action' => 'deactivate', 'reason' => 'Requested temporary business closure.',
        ])->assertRedirect();
        $this->assertSame('inactive',$tenant->fresh()->status);
        $this->assertSame('active',$tenant->fresh()->lifecycle_previous_status);
        $this->assertSame('paused',$tenant->fresh()->subscription->status);
        $this->assertSame('asset_review',$tenant->fresh()->subscription->source);

        $this->actingAs($admin)->patch(route('platform.tenants.lifecycle.update',$tenant),[
            'action' => 'reactivate', 'reason' => 'Business owner requested reopening.',
        ])->assertRedirect();
        $this->assertSame('active',$tenant->fresh()->status);
        $this->assertSame('active',$tenant->fresh()->subscription->status);
        $this->assertSame('asset_review',$tenant->fresh()->subscription->source);
        $this->assertSame($originalEnd,$tenant->fresh()->subscription->ends_at->toDateTimeString());

        $this->actingAs($admin)->patch(route('platform.tenants.lifecycle.update',$tenant),[
            'action' => 'suspend', 'reason' => 'Compliance investigation.',
        ])->assertRedirect();
        $this->assertSame('suspended',$tenant->fresh()->status);
        $this->actingAs($admin)->patch(route('platform.tenants.lifecycle.update',$tenant),[
            'action' => 'reactivate', 'reason' => 'Investigation resolved.',
        ])->assertRedirect();
        $this->assertSame('active',$tenant->fresh()->status);
    }

    public function test_only_permanent_super_admin_can_archive_and_restore_with_password_confirmation(): void
    {
        $admin = $this->admin();
        $operations = $this->admin('operations');
        $temporary = $this->admin('super_admin',true);
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner,['name' => 'Business To Archive']);
        $branchId = $tenant->branches()->firstOrFail()->id;
        $slug = $tenant->slug;

        $this->actingAs($operations)->post(route('platform.tenants.archive',$tenant),[
            'confirmation'=>$slug,'admin_password'=>'password','reason'=>'A clear closure decision.',
        ])->assertForbidden();
        $this->actingAs($temporary)->post(route('platform.tenants.archive',$tenant),[
            'confirmation'=>$slug,'admin_password'=>'password','reason'=>'A clear closure decision.',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('platform.tenants.archive',$tenant),[
            'confirmation'=>'wrong-slug','admin_password'=>'password','reason'=>'A clear closure decision.',
        ])->assertSessionHasErrors('confirmation');
        $this->assertNull($tenant->fresh()->deleted_at);

        $this->actingAs($admin)->post(route('platform.tenants.archive',$tenant),[
            'confirmation'=>$slug,'admin_password'=>'password','reason'=>'A clear closure decision.',
        ])->assertRedirect(route('platform.tenants.archived'));

        $archived = Tenant::withTrashed()->findOrFail($tenant->id);
        $this->assertTrue($archived->trashed());
        $this->assertDatabaseHas('branches',['id'=>$branchId,'tenant_id'=>$tenant->id]);
        $this->actingAs($owner)->get(route('business.dashboard',$tenant))->assertNotFound();
        $this->actingAs($admin)->get(route('platform.tenants.index'))
            ->assertDontSee('Business To Archive');

        $this->actingAs($admin)->get(route('platform.tenants.archived'))
            ->assertOk()->assertSee('Business To Archive');
        $this->actingAs($admin)->post(route('platform.tenants.restore',$tenant->id),[
            'confirmation'=>$slug,'admin_password'=>'password','reason'=>'Requested reopening safely.',
        ])->assertRedirect();

        $this->assertNotNull(Tenant::find($tenant->id));
        $this->assertSame('trial',Tenant::findOrFail($tenant->id)->status);
        $this->actingAs($owner)->get(route('business.dashboard',$tenant))->assertOk();
        $this->assertDatabaseHas('audit_logs',['tenant_id'=>$tenant->id,'action'=>'tenant.archived']);
        $this->assertDatabaseHas('audit_logs',['tenant_id'=>$tenant->id,'action'=>'tenant.restored']);
    }

    public function test_tenant_export_requires_permanent_admin_and_excludes_private_notes(): void
    {
        $admin = $this->admin();
        $operations = $this->admin('operations');
        $tenant = $this->tenant('Exportable Store');
        $tenant->notes()->create(['author_id'=>$admin->id,'body'=>'PRIVATE_ADMIN_NOTE_STRING']);
        $owner = $tenant->users()->wherePivot('is_owner',true)->firstOrFail();

        $this->actingAs($operations)->post(route('platform.tenants.export',$tenant),[
            'admin_password'=>'password',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('platform.tenants.export',$tenant),[
            'admin_password'=>'wrong',
        ])->assertSessionHasErrors('admin_password');

        $response = $this->actingAs($admin)->post(route('platform.tenants.export',$tenant),[
            'admin_password'=>'password',
        ]);
        $response->assertOk()->assertHeader('content-type','application/json; charset=UTF-8');
        $json = $response->streamedContent();
        $data = json_decode($json,true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('pos-cloud-tenant-snapshot-v1',$data['format']);
        $this->assertSame('Exportable Store',$data['business']['name']);
        $this->assertSame($owner->email,$data['members'][0]['email']);
        $this->assertCount(1,$data['branches']);
        $this->assertStringNotContainsString('PRIVATE_ADMIN_NOTE_STRING',$json);
        $this->assertStringNotContainsString('password',$json);
        $this->assertDatabaseHas('audit_logs',[
            'tenant_id'=>$tenant->id,'action'=>'tenant.export.downloaded',
        ]);
    }

    public function test_inactive_business_cannot_be_activated_through_billing_or_payment_review(): void
    {
        $admin = $this->admin('operations');
        $billing = $this->admin('billing');
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner,['name'=>'Access Restricted']);
        $plan = Plan::where('slug','business')->firstOrFail();

        $this->actingAs($admin)->patch(route('platform.tenants.lifecycle.update',$tenant),[
            'action'=>'deactivate','reason'=>'Account paused for compliance review.',
        ])->assertRedirect();

        $this->assertSame('inactive',$tenant->fresh()->status);

        $this->actingAs($billing)->post(route('platform.tenants.plan.activate',$tenant),[
            'plan_id'=>$plan->id,
        ])->assertSessionHasErrors('plan_id');
        $this->assertSame('inactive',$tenant->fresh()->status);
        $this->assertSame('paused',$tenant->fresh()->subscription->status);

        \App\Models\PaymentMethod::where('code','asset')->update(['is_enabled'=>true]);
        $plan->update(['is_active'=>true,'is_public'=>true,'monthly_price_minor'=>150000]);
        Storage::fake('local');

        $this->actingAs($owner)->post(route('business.subscription.payments.store',$tenant),[
            'plan_id'=>$plan->id,
            'billing_cycle'=>'monthly',
            'evidence'=>UploadedFile::fake()->image('receipt.png'),
        ])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('subscription_payment_requests',0);

        $request = \App\Models\SubscriptionPaymentRequest::create([
            'tenant_id'=>$tenant->id, 'plan_id'=>$plan->id,
            'submitted_by'=>$owner->id,'method'=>'asset','billing_cycle'=>'monthly',
            'requested_amount_minor'=>150000,'currency'=>'LKR','status'=>'pending',
        ]);
        $this->actingAs($billing)->post(route('platform.payments.review.approve',$request),[
            'approved_amount'=>'1500.00',
            'review_reference'=>'BANK-BLOCKED-0001',
            'confirm_funds_checked'=>'1',
        ])->assertSessionHasErrors('payment');

        $this->assertSame('pending',$request->fresh()->status);
        $this->assertSame('inactive',$tenant->fresh()->status);
    }

    public function test_upgrade_backfills_recoverable_legacy_suspended_accounts(): void
    {
        $trialTenant = $this->tenant('Previously Suspended Trial');
        $trialTenant->forceFill([
            'status' => 'suspended',
            'lifecycle_previous_status' => null,
        ])->save();
        $trialTenant->subscription()->update(['status' => 'paused', 'source' => 'trial']);

        $paidTenant = $this->tenant('Previously Suspended Subscriber');
        $paidTenant->forceFill([
            'status' => 'suspended',
            'lifecycle_previous_status' => null,
        ])->save();
        $paidTenant->subscription()->update(['status' => 'paused', 'source' => 'card_review']);

        $migration = require database_path('migrations/2026_11_21_000008_backfill_recoverable_suspended_tenants.php');
        $migration->up();

        $this->assertSame('trial', $trialTenant->fresh()->lifecycle_previous_status);
        $this->assertSame('active', $paidTenant->fresh()->lifecycle_previous_status);
        $this->assertSame('suspended', $trialTenant->fresh()->status);
        $this->assertSame('paused', $paidTenant->fresh()->subscription->status);
    }
}

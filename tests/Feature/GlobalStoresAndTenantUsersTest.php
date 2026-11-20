<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\StoreDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class GlobalStoresAndTenantUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'operations', bool $temporary = false): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => $role,
            'temporary_admin_expires_at' => $temporary ? now()->addHour() : null,
        ]);
    }

    private function tenant(string $name = 'Main Business', ?User $owner = null): Tenant
    {
        return app(CreateTenant::class)->handle(
            $owner ?? User::factory()->create(), ['name' => $name]
        );
    }

    private function employee(Tenant $tenant, string $role = 'cashier', ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'is_owner' => false]);
        DB::table('role_user')->insert([
            'role_id' => Role::where('tenant_id', $tenant->id)->where('slug', $role)->firstOrFail()->id,
            'user_id' => $user->id, 'tenant_id' => $tenant->id,
        ]);
        return $user;
    }

    public function test_global_store_directory_searches_and_filters_and_is_platform_only(): void
    {
        $staff = $this->employee($this->tenant('Alpha Shop'), 'manager');
        $first = $staff->tenants()->firstOrFail();
        $first->update(['country' => 'LK']);
        $store = $first->branches()->firstOrFail();
        $this->actingAs($this->admin())->patch(route('platform.stores.update', $store), [
            'name' => 'Colombo Main', 'status' => 'active', 'manager_user_id' => $staff->id,
            'reason' => 'Manager assigned for store launch.',
        ])->assertRedirect();

        $other = $this->tenant('Beta Shop');
        $other->update(['country' => 'US']);

        $this->actingAs($this->admin('support'))->get(route('platform.stores.index', [
            'search' => $staff->email, 'country' => 'LK', 'status' => 'active',
            'tenant_id' => $first->id, 'sort' => 'name',
        ]))->assertOk()->assertSee('Colombo Main')->assertDontSee('Beta Shop');
        $this->actingAs($this->admin())->get(route('platform.stores.index', [
            'country' => 'US',
        ]))->assertOk()->assertSee('Beta Shop')->assertDontSee('Colombo Main');

        $this->actingAs($staff)->get(route('platform.stores.index'))->assertForbidden();
        $this->actingAs($this->admin('billing'))->get(route('platform.stores.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('platform.stores.index', [
            'sort' => 'name; DROP TABLE branches',
        ]))->assertSessionHasErrors('sort');
    }

    public function test_store_manager_must_belong_to_same_tenant_and_have_manager_role(): void
    {
        $tenant = $this->tenant();
        $store = $tenant->branches()->firstOrFail();
        $cashier = $this->employee($tenant, 'cashier');
        $manager = $this->employee($tenant, 'manager');
        $other = $this->employee($this->tenant('Other Business'), 'manager');
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('platform.stores.update', $store), [
            'name' => $store->name, 'status' => 'active', 'manager_user_id' => $other->id,
            'reason' => 'Cross-tenant manager forbidden.',
        ])->assertSessionHasErrors('manager_user_id');

        $this->actingAs($admin)->patch(route('platform.stores.update', $store), [
            'name' => $store->name, 'status' => 'active', 'manager_user_id' => $cashier->id,
            'reason' => 'Cashier role cannot manage store.',
        ])->assertSessionHasErrors('manager_user_id');

        $this->actingAs($admin)->patch(route('platform.stores.update', $store), [
            'name' => 'Updated Store', 'status' => 'active',
            'manager_user_id' => $manager->id, 'timezone' => 'Asia/Colombo',
            'reason' => 'Assigned trained manager to branch.',
        ])->assertRedirect();

        $this->assertSame($manager->id, $store->fresh()->manager_user_id);
        $this->assertTrue($store->staff()->whereKey($manager->id)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'subject_type' => Branch::class,
            'subject_id' => $store->id, 'action' => 'store.updated',
        ]);
    }

    public function test_store_status_preserves_default_and_active_plan_limits(): void
    {
        $tenant = $this->tenant();
        $default = $tenant->branches()->firstOrFail();
        $extra = $tenant->branches()->create([
            'name' => 'Branch Two', 'code' => 'TWO', 'is_active' => true,
            'is_default' => false, 'status' => 'active',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('platform.stores.update', $default), [
            'name' => 'Main', 'status' => 'suspended',
            'reason' => 'Default store cannot be restricted yet.',
        ])->assertSessionHasErrors('status');

        $this->actingAs($admin)->patch(route('platform.stores.update', $extra), [
            'name' => 'Branch Two', 'status' => 'suspended',
            'reason' => 'Store temporarily suspended.',
        ])->assertRedirect();

        $this->assertSame('suspended', $extra->fresh()->status);
        $this->assertFalse($extra->fresh()->is_active);
        $tenant->subscription->plan->update(['max_branches' => 1]);
        $this->actingAs($admin)->patch(route('platform.stores.update', $extra), [
            'name' => 'Branch Two', 'status' => 'active',
            'reason' => 'Restore branch access.',
        ])->assertSessionHasErrors('status');

        $this->assertSame('suspended', $extra->fresh()->status);
        $this->assertSame('active', $default->fresh()->status);
    }

    public function test_store_staff_assignment_rejects_cross_tenant_and_duplicate_assignments(): void
    {
        $tenant = $this->tenant();
        $store = $tenant->branches()->firstOrFail();
        $employee = $this->employee($tenant);
        $outsider = $this->employee($this->tenant('Another Tenant'));
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('platform.stores.staff.assign', $store), [
            'user_id' => $outsider->id,
        ])->assertSessionHasErrors('user_id');

        $this->actingAs($admin)->post(route('platform.stores.staff.assign', $store), [
            'user_id' => $employee->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('branch_user', [
            'tenant_id' => $tenant->id, 'branch_id' => $store->id, 'user_id' => $employee->id,
        ]);
        $this->actingAs($admin)->post(route('platform.stores.staff.assign', $store), [
            'user_id' => $employee->id,
        ])->assertSessionHasErrors('user_id');

        $this->actingAs($admin)->delete(route('platform.stores.staff.unassign', [$store, $employee]))
            ->assertRedirect();

        $this->assertDatabaseMissing('branch_user', [
            'branch_id' => $store->id, 'user_id' => $employee->id,
        ]);
        $this->assertTrue($tenant->users()->whereKey($employee->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.staff.unassigned']);
    }

    public function test_store_device_registers_only_an_inventory_record_and_is_tenant_scoped(): void
    {
        $tenant = $this->tenant();
        $store = $tenant->branches()->firstOrFail();
        $otherStore = $this->tenant('Other Tenant')->branches()->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('platform.stores.devices.store', $store), [
            'name' => 'Front Desk POS', 'type' => 'pos_terminal',
            'manufacturer' => 'Demo Brand', 'serial_number' => 'SN-100',
        ])->assertRedirect();

        $device = StoreDevice::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('registered', $device->status);
        $this->assertNull($device->last_seen_at);

        $this->actingAs($admin)->post(route('platform.stores.devices.store', $store), [
            'name' => 'Duplicate POS', 'type' => 'pos_terminal', 'serial_number' => 'SN-100',
        ])->assertSessionHasErrors('serial_number');

        $this->actingAs($admin)->patch(route('platform.stores.devices.update', [
            $otherStore, $device,
        ]), ['status' => 'retired', 'reason' => 'Attempt wrong tenant'])->assertNotFound();

        $this->actingAs($admin)->patch(route('platform.stores.devices.update', [
            $store, $device,
        ]), ['status' => 'retired', 'reason' => 'Device removed from service.'])->assertRedirect();
        $this->assertSame('retired', $device->fresh()->status);
    }

    public function test_store_overrides_use_allowlisted_keys_and_cannot_inject_new_settings(): void
    {
        $tenant = $this->tenant();
        $store = $tenant->branches()->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('platform.stores.settings', $store), [
            'receipt_footer' => 'Thank you', 'low_stock_threshold' => '5',
            'print_receipts' => '0', 'api_secret' => 'should-not-be-stored',
        ])->assertRedirect();

        $overrides = $store->fresh()->settings_overrides;
        $this->assertSame('Thank you', $overrides['receipt_footer']);
        $this->assertSame(5, $overrides['low_stock_threshold']);
        $this->assertFalse($overrides['print_receipts']);
        $this->assertArrayNotHasKey('api_secret', $overrides);
    }

    public function test_memberships_are_scoped_and_owner_cannot_be_blocked(): void
    {
        $admin = $this->admin();
        $first = $this->tenant('First Business');
        $second = $this->tenant('Second Business');
        $member = User::factory()->create();
        $this->employee($first, 'cashier', $member);
        $this->employee($second, 'manager', $member);
        $role = Role::where('tenant_id', $first->id)->where('slug', 'manager')->firstOrFail();

        $this->actingAs($admin)->patch(route('platform.tenant-users.update', [$first, $member]), [
            'status' => 'blocked', 'role_id' => $role->id, 'reason' => 'Security hold for this business.',
        ])->assertRedirect();

        $this->assertSame('blocked', $first->users()->findOrFail($member->id)->pivot->status);
        $this->assertSame('active', $second->users()->findOrFail($member->id)->pivot->status);
        $this->assertFalse($first->users()->whereKey($member->id)->wherePivot('status', 'active')->exists());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $first->id, 'action' => 'tenant.user.updated', 'subject_id' => $member->id,
        ]);

        $otherRole = Role::where('tenant_id', $second->id)->where('slug', 'cashier')->firstOrFail();
        $this->actingAs($admin)->patch(route('platform.tenant-users.update', [$first, $member]), [
            'status' => 'active', 'role_id' => $otherRole->id, 'reason' => 'Cross tenant escalation',
        ])->assertSessionHasErrors('role_id');

        $owner = $first->users()->wherePivot('is_owner', true)->firstOrFail();
        $this->actingAs($admin)->patch(route('platform.tenant-users.update', [$first, $owner]), [
            'status' => 'blocked', 'role_id' => $role->id, 'reason' => 'Not allowed',
        ])->assertForbidden();
    }

    public function test_tenant_staff_directory_filters_by_store_role_status_and_business(): void
    {
        $first = $this->tenant('Filter Business');
        $second = $this->tenant('Unrelated Business');
        $manager = $this->employee($first, 'manager');
        $cashier = $this->employee($second, 'cashier');
        $store = $first->branches()->firstOrFail();
        $store->staff()->attach($manager->id, ['tenant_id' => $first->id]);

        $this->actingAs($this->admin('support'))->get(route('platform.tenant-users.index', [
            'tenant_id' => $first->id, 'store_id' => $store->id,
            'role' => 'manager', 'status' => 'active',
            'search' => $manager->email,
        ]))->assertOk()->assertSee($manager->email)->assertDontSee($cashier->email);
        $this->actingAs($this->admin())->get(route('platform.tenant-users.index', [
            'role' => 'cashier',
        ]))->assertOk()->assertSee($cashier->email)->assertDontSee($manager->email);
        $this->actingAs($cashier)->get(route('platform.tenant-users.index'))->assertForbidden();
    }

    public function test_login_history_tracks_success_failure_without_storing_password(): void
    {
        $user = User::factory()->create(['email' => 'login-test@example.test']);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect-value'])
            ->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id, 'outcome' => 'failed',
        ]);
        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id, 'outcome' => 'success',
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame(2, LoginEvent::where('user_id', $user->id)->count());
        $this->assertStringNotContainsString('incorrect-value', LoginEvent::where('outcome', 'failed')
            ->firstOrFail()->user_agent ?? '');
    }

    public function test_permanent_super_admin_can_require_global_reset_and_revoke_sessions(): void
    {
        Notification::fake();

        $super = $this->admin('super_admin');
        $staff = User::factory()->create();
        $first = $this->tenant('Security Business A');
        $second = $this->tenant('Security Business B');
        $this->employee($first, 'cashier', $staff);
        $this->employee($second, 'manager', $staff);

        $temporary = $this->admin('super_admin', true);
        $this->actingAs($temporary)->post(route('platform.tenant-users.force-reset', [$first, $staff]), [
            'admin_password' => 'password', 'reason' => 'Unauthorized temporary reset.',
        ])->assertForbidden();
        $this->actingAs($this->admin('operations'))->post(route('platform.tenant-users.force-reset', [$first, $staff]), [
            'admin_password' => 'password', 'reason' => 'Unauthorized operations reset.',
        ])->assertForbidden();

        $this->actingAs($super)->post(route('platform.tenant-users.force-reset', [$first, $staff]), [
            'admin_password' => 'wrong', 'reason' => 'Security incident review.',
        ])->assertSessionHasErrors('admin_password');

        $this->actingAs($super)->post(route('platform.tenant-users.force-reset', [$first, $staff]), [
            'admin_password' => 'password', 'reason' => 'Security incident review.',
        ])->assertRedirect();

        $this->assertNotNull($staff->fresh()->force_password_reset_at);
        $this->assertSame(1, $staff->fresh()->auth_session_version);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $first->id, 'action' => 'tenant.user.password_reset_required',
        ]);
        Notification::assertSentTo($staff, \App\Notifications\PosPasswordResetNotification::class);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('password.request'));

        $this->actingAs($super)->post(route('platform.tenant-users.revoke-sessions', [$first, $staff]), [
            'admin_password' => 'password', 'reason' => 'Lost employee laptop.',
        ])->assertRedirect();
        $this->assertSame(2, $staff->fresh()->auth_session_version);
        $this->assertSame('active', $second->users()->findOrFail($staff->id)->pivot->status);
    }

    public function test_account_session_version_revocation_works_for_non_database_session_drivers(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenant('Versioned Access', $owner);

        $this->actingAs($owner)->withSession(['account_session_version' => 0])
            ->get(route('business.dashboard', $tenant))->assertOk();

        $owner->forceFill(['auth_session_version' => 1])->save();

        $this->actingAs($owner)->withSession(['account_session_version' => 0])
            ->get(route('business.dashboard', $tenant))->assertRedirect(route('login'));
    }

    public function test_password_reset_clears_forced_reset_without_reopening_revoked_sessions(): void
    {
        $staff = User::factory()->create([
            'force_password_reset_at' => now(),
            'auth_session_version' => 1,
        ]);
        $token = Password::createToken($staff);

        $this->post(route('password.update'), [
            'email' => $staff->email,
            'token' => $token,
            'password' => 'NewSuperSecurePassword123',
            'password_confirmation' => 'NewSuperSecurePassword123',
        ])->assertRedirect(route('login'));

        $this->assertNull($staff->fresh()->force_password_reset_at);
        $this->assertSame(2, $staff->fresh()->auth_session_version);
        $this->assertTrue(Hash::check('NewSuperSecurePassword123', $staff->fresh()->password));
    }

    public function test_mail_variable_renderer_uses_configured_placeholders_without_executing_code(): void
    {
        config()->set('pos_emails.staff_invitation.body',
            'Hello {{employee_email}}. {{business_name}} {{invitation_url}} {{not_supplied}}');
        $rendered = \App\Services\PosEmailVariables::render('staff_invitation', 'body', [
            'employee_email' => 'staff@example.test', 'business_name' => 'Great Shop',
            'invitation_url' => 'https://example.test/invite/abc',
        ]);
        $this->assertSame(
            'Hello staff@example.test. Great Shop https://example.test/invite/abc {{not_supplied}}',
            $rendered
        );
    }
}

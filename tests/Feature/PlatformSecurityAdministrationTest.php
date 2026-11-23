<?php

namespace Tests\Feature;

use App\Models\PlatformRole;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Models\UserSecuritySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformSecurityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): User
    {
        return User::factory()->create(['is_platform_admin' => true, 'platform_role' => $role]);
    }

    public function test_granular_roles_limit_access_even_with_a_matching_scope(): void
    {
        $super = $this->admin();
        $ops = $this->admin('operations');
        $this->actingAs($ops)->get(route('platform.security.roles.index'))->assertForbidden();

        $this->actingAs($super)->post(route('platform.security.roles.store'), [
            'name' => 'Store Viewer', 'slug' => 'store_viewer',
            'scopes' => ['operations'], 'permissions' => ['dashboard.view', 'stores.view'],
            'is_active' => '1', 'admin_password' => 'password',
        ])->assertRedirect();

        $role = PlatformRole::where('slug', 'store_viewer')->firstOrFail();
        $candidate = User::factory()->create();
        $this->actingAs($super)->post(route('platform.admins.store'), [
            'email' => $candidate->email, 'role' => 'custom_'.$role->id,
            'admin_password' => 'password',
        ])->assertRedirect();

        $this->actingAs($candidate->fresh())->get(route('platform.dashboard'))->assertOk();
        $this->actingAs($candidate->fresh())->get(route('platform.stores.index'))->assertOk();
        $this->actingAs($candidate->fresh())->get(route('platform.stores.create'))->assertForbidden();
        $this->actingAs($candidate->fresh())->get(route('platform.tenants.index'))->assertForbidden();
        $this->actingAs($candidate->fresh())->get(route('platform.payments.review.index'))->assertForbidden();
        $this->actingAs($candidate->fresh())->get(route('platform.admins.index'))->assertForbidden();

        $this->actingAs($super)->post(route('platform.security.roles.store'), [
            'name' => 'Bad', 'slug' => 'bad_role',
            'scopes' => ['auditor'], 'permissions' => ['billing.manage'],
            'is_active' => '1', 'admin_password' => 'password',
        ])->assertSessionHasErrors('permissions');
    }

    public function test_updating_custom_role_invalidates_assigned_admin_sessions(): void
    {
        $super = $this->admin();
        $role = PlatformRole::create([
            'name' => 'Reporter', 'slug' => 'reporter',
            'scopes' => ['auditor'], 'permissions' => ['dashboard.view', 'reports.view'],
            'is_active' => true,
        ]);
        $analyst = $this->admin('custom_'.$role->id);
        $this->actingAs($analyst)->get(route('platform.reports.index'))->assertOk();
        $this->actingAs($analyst)->get(route('platform.audit.index'))->assertForbidden();

        $this->actingAs($super)->put(route('platform.security.roles.update', $role), [
            'name' => 'Reporter', 'slug' => 'reporter',
            'scopes' => ['auditor'], 'permissions' => ['dashboard.view','audit.view'],
            'is_active' => '1', 'admin_password' => 'password',
        ])->assertRedirect();

        $this->assertSame(1, $analyst->fresh()->auth_session_version);
        $this->actingAs($analyst->fresh())->get(route('platform.reports.index'))->assertForbidden();
        $this->actingAs($analyst->fresh())->get(route('platform.audit.index'))->assertOk();
    }

    public function test_password_verified_session_revocation_logs_out_current_device(): void
    {
        $user = User::factory()->create();
        $this->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect();
        $this->get(route('onboarding.create'))->assertOk();

        $session = UserSecuritySession::where('user_id',$user->id)->firstOrFail();
        $this->get(route('security.sessions'))->assertOk()->assertSee('This device');
        $this->delete(route('security.sessions.revoke',$session), [
            'password' => 'incorrect',
        ])->assertSessionHasErrors('password');
        $this->assertNull($session->fresh()->revoked_at);

        $this->delete(route('security.sessions.revoke',$session), [
            'password' => 'password',
        ])->assertRedirect(route('login'));
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertGuest();
    }

    public function test_login_failure_lock_and_security_alert_review(): void
    {
        $admin = $this->admin();
        for ($i=0;$i<5;$i++) {
            $this->post(route('login'), [
                'email' => $admin->email,'password' => 'wrongpassword',
            ])->assertSessionHasErrors('email');
        }
        $this->assertDatabaseHas('security_alerts',[
            'user_id'=>$admin->id,'type'=>'repeated_login_failures',
        ]);
        $this->post(route('login'), [
            'email'=>$admin->email,'password'=>'password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->get(route('platform.security.index'))->assertOk();
        $alert=SecurityAlert::firstOrFail();
        $this->actingAs($admin)->post(route('platform.security.alerts.resolve',$alert),[
            'reason'=>'Reviewed the suspicious login history.',
        ])->assertRedirect();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_expired_password_policy_restricts_admin(): void
    {
        config(['platform_security.password_expiry_days'=>90]);
        $user=$this->admin();
        $user->forceFill(['password_changed_at'=>now()->subDays(100)])->save();
        $this->actingAs($user)->get(route('platform.dashboard'))
            ->assertRedirect(route('password.request'));
    }
}

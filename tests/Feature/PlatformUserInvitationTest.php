<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\PlatformUserInvitation;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUserInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_admin_can_invite_when_public_signups_are_disabled(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'operations']);
        PlatformSetting::updateOrCreate(['key' => 'registration.enabled'], ['value' => '0']);

        $this->actingAs($admin)->get(route('platform.users.index'))->assertOk();
        $this->actingAs($admin)->post(route('platform.users.invite'), [
            'name' => 'Invited Owner', 'email' => 'invited@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_user_invitations', ['email' => 'invited@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'invited@example.com']);
    }

    public function test_support_admin_can_view_directory_but_cannot_invite(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'support']);

        $this->actingAs($admin)->get(route('platform.users.index'))->assertOk();
        $this->actingAs($admin)->post(route('platform.users.invite'), [
            'name' => 'Unauthorized', 'email' => 'new@example.com',
        ])->assertForbidden();
    }

    public function test_invitation_is_one_time_and_verifies_email_when_accepted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $token = str_repeat('a', 64);
        PlatformSetting::updateOrCreate(['key' => 'registration.enabled'], ['value' => '0']);
        $invitation = PlatformUserInvitation::create([
            'name' => 'New Owner', 'email' => 'owner@example.com',
            'invited_by' => $admin->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(2),
        ]);

        $this->get(route('platform-invite.show', $token))->assertOk();
        $this->post(route('platform-invite.accept', $token), [
            'password' => 'SecurePassword2026',
            'password_confirmation' => 'SecurePassword2026',
        ])->assertRedirect(route('onboarding.create'));

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->hasVerifiedEmail());
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Invited Business']);
        $this->assertTrue($owner->isOwnerOf($tenant));

        $this->post(route('logout'))->assertRedirect();
        $this->post(route('platform-invite.accept', $token), [
            'password' => 'AnotherSecurePassword2026',
            'password_confirmation' => 'AnotherSecurePassword2026',
        ])->assertNotFound();
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $token = str_repeat('x', 64);
        PlatformUserInvitation::create([
            'name' => 'Expired Owner', 'email' => 'expired@example.com',
            'invited_by' => $admin->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('platform-invite.show', $token))->assertNotFound();
        $this->post(route('platform-invite.accept', $token), [
            'password' => 'SecurePassword2026',
            'password_confirmation' => 'SecurePassword2026',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'expired@example.com']);
    }
}

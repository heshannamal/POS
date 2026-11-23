<?php

namespace Tests\Feature;

use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'super_admin']);
    }

    public function test_totp_matches_known_sha1_vector_and_enforces_valid_codes(): void
    {
        $totp = app(Totp::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $totp->code($secret, 59));
        $this->assertTrue($totp->verify($secret, '287082', 59));
        $this->assertFalse($totp->verify($secret, 'abcdef', 59));
        $this->assertSame(32, strlen($totp->generateSecret()));
    }

    public function test_super_admin_must_enroll_and_confirm_totp_before_platform_access(): void
    {
        config(['platform_security.require_2fa' => true]);
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('platform.dashboard'))->assertRedirect(route('security.setup'));
        $this->get(route('security.setup'))->assertOk()->assertSee('Manual setup key');

        $secret = session('pending_two_factor_secret');
        $this->assertNotEmpty($secret);
        $this->post(route('security.enable'), [
            'password' => 'wrong', 'code' => app(Totp::class)->code($secret, time()),
        ])->assertSessionHasErrors('password');

        $this->post(route('security.enable'), [
            'password' => 'password', 'code' => app(Totp::class)->code($secret, time()),
        ])->assertRedirect(route('security.recovery'));

        $admin->refresh();
        $this->assertSame($secret, $admin->two_factor_secret);
        $this->assertNotEquals($secret, $admin->getRawOriginal('two_factor_secret'));
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->get(route('platform.dashboard'))->assertOk();
        $this->post(route('security.disable'), [
            'password' => 'password', 'code' => app(Totp::class)->code($secret, time()),
        ])->assertSessionHasErrors('code');
    }

    public function test_enrolled_admin_needs_second_factor_and_recovery_code_is_one_time(): void
    {
        config(['platform_security.require_2fa' => true]);
        $admin = $this->superAdmin();
        $secret = app(Totp::class)->generateSecret();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [Hash::make('ABCDEF1234')],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(route('login'), [
            'email' => $admin->email, 'password' => 'password',
        ])->assertRedirect(route('security.challenge'));

        $this->get(route('platform.dashboard'))->assertRedirect(route('security.challenge'));
        $this->post(route('security.verify'), ['recovery_code' => 'ABCDEF1234'])
            ->assertRedirect(route('platform.dashboard'));
        $this->assertSame([], $admin->fresh()->two_factor_recovery_codes);
        $this->get(route('platform.dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login'), [
            'email' => $admin->email, 'password' => 'password',
        ])->assertRedirect(route('security.challenge'));

        $this->post(route('security.verify'), ['recovery_code' => 'ABCDEF1234'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseHas('security_alerts', [
            'user_id' => $admin->id, 'type' => 'two_factor_failure',
        ]);
        $this->post(route('security.verify'), [
            'code' => app(Totp::class)->code($secret, time()),
        ])->assertRedirect(route('platform.dashboard'));
    }
}

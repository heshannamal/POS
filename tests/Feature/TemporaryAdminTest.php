<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemporaryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_random_verified_expiring_admin_in_development(): void
    {
        $exit = Artisan::call('platform:temporary-login', ['--hours' => 2]);

        $this->assertSame(0, $exit);
        $user = User::query()->whereNotNull('temporary_admin_expires_at')->firstOrFail();

        $this->assertTrue($user->is_platform_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->isTemporaryAdminExpired());
        $this->assertTrue($user->temporary_admin_expires_at->greaterThan(now()->addMinutes(100)));
        $this->assertTrue($user->temporary_admin_expires_at->lessThan(now()->addMinutes(130)));
        $this->assertStringStartsWith('temp-admin-', $user->email);
    }

    public function test_invalid_duration_cannot_create_an_account(): void
    {
        $this->assertSame(1, Artisan::call('platform:temporary-login', ['--hours' => 999]));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_temporary_login_command_is_disabled_in_production(): void
    {
        config(['app.env' => 'production']);

        try {
            $this->assertSame(1, Artisan::call('platform:temporary-login'));
            $this->assertDatabaseCount('users', 0);
        } finally {
            config(['app.env' => 'testing']);
        }
    }

    public function test_expired_admin_cannot_login_or_access_platform_routes(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'temporary_admin_expires_at' => now()->subMinute(),
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get(route('platform.dashboard'))->assertForbidden();
    }

    public function test_valid_temporary_admin_can_use_platform_until_expiry(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'temporary_admin_expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)->get(route('platform.dashboard'))->assertOk();

        $user->forceFill(['temporary_admin_expires_at' => now()->subMinute()])->save();
        $this->actingAs($user->fresh())->get(route('platform.dashboard'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_platform_admin'=>true,
            'platform_role'=>'super_admin',
        ]);
    }

    private function login(User $admin): void
    {
        config(['platform_security.require_2fa'=>true]);
        $this->post(route('login'),[
            'email'=>$admin->email,
            'password'=>'password',
        ])->assertRedirect(route('security.challenge'));
    }

    private function setCode(User $admin,string $code='123456',int $expiresInSeconds=600): void
    {
        DB::table('email_login_codes')->updateOrInsert(
            ['user_id'=>$admin->id],
            [
                'code_hash'=>hash_hmac('sha256',$code,(string) config('app.key')),
                'expires_at'=>now()->addSeconds($expiresInSeconds),
                'last_sent_at'=>now()->subMinutes(2),
                'attempts'=>0,'created_at'=>now(),'updated_at'=>now(),
            ]
        );
    }

    public function test_admin_is_redirected_to_email_challenge_and_six_digit_code_is_required(): void
    {
        $admin=$this->superAdmin();
        $this->login($admin);
        $this->get(route('security.challenge'))->assertOk()->assertSee($admin->email);
        $this->get(route('platform.dashboard'))->assertRedirect(route('security.challenge'));
        $this->post(route('security.verify'),['code'=>'12345'])
            ->assertSessionHasErrors('code');
        $this->post(route('security.verify'),['code'=>'abcdef'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseHas('email_login_codes',['user_id'=>$admin->id]);
    }

    public function test_incorrect_code_is_rejected_and_valid_code_is_single_use(): void
    {
        $admin=$this->superAdmin();
        $this->login($admin);
        $this->setCode($admin);
        $this->post(route('security.verify'),['code'=>'000000'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseHas('security_alerts',[
            'user_id'=>$admin->id,'type'=>'two_factor_failure',
        ]);
        $this->post(route('security.verify'),['code'=>'123456'])
            ->assertRedirect(route('platform.dashboard'));
        $this->assertDatabaseMissing('email_login_codes',['user_id'=>$admin->id]);
        $this->get(route('platform.dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->login($admin);
        // A code already consumed in a previous session cannot be reused.
        $this->post(route('security.verify'),['code'=>'123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_expired_and_over_attempt_limit_codes_are_denied(): void
    {
        $admin=$this->superAdmin();
        $this->login($admin);
        $this->setCode($admin,'123456',-1);
        $this->post(route('security.verify'),['code'=>'123456'])
            ->assertSessionHasErrors('code');
        $this->setCode($admin);
        DB::table('email_login_codes')->where('user_id',$admin->id)
            ->update(['attempts'=>5]);
        $this->post(route('security.verify'),['code'=>'123456'])
            ->assertSessionHasErrors('code');
        $this->get(route('platform.dashboard'))->assertRedirect(route('security.challenge'));
    }

    public function test_pending_email_verification_does_not_block_signout(): void
    {
        $admin=$this->superAdmin();
        $this->login($admin);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}

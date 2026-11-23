<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CommunicationTemplate;
use App\Models\SecurityAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationIntegrationTest extends TestCase
{
    // Match the project's existing transactional test setup.
    use RefreshDatabase;

    public function test_tenant_notification_event_service_creates_scoped_inbox_with_email_off(): void
    {
        $owner = User::factory()->create();
        $platformAdmin = User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'operations',
        ]);
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Integrated Shop']);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id, 'action' => 'tenant.created',
        ]);
        $audit = AuditLog::where('tenant_id', $tenant->id)
            ->where('action', 'tenant.created')->firstOrFail();
        app(\App\Services\CommunicationCenter::class)->eventFromAudit($audit);
        app(\App\Services\CommunicationCenter::class)->eventFromAudit($audit);

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $owner->id, 'tenant_id' => $tenant->id,
            'event_key' => 'business.created',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $platformAdmin->id,
            'event_key' => 'business.created',
        ]);
        $this->assertDatabaseCount('communication_deliveries',0);
    }

    public function test_security_alert_service_notifies_platform_admin(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'super_admin',
        ]);
        $alert = SecurityAlert::create([
            'user_id' => $admin->id,
            'type' => 'repeated_login_failures',
            'ip_address' => '192.0.2.7',
        ]);
        app(\App\Services\CommunicationCenter::class)->securityAlert($alert);


        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id'=>$admin->id,
            'tenant_id'=>null,
            'event_key'=>'security.alert',
        ]);
        $this->assertSame(1, UserNotification::where('event_key','security.alert')->count());
    }

    public function test_custom_draft_is_editable_but_does_not_register_an_automatic_notification_trigger(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'super_admin',
        ]);

        $this->actingAs($admin)->get(route('platform.communications.templates.create'))
            ->assertOk()->assertSee('New custom template');

        $this->actingAs($admin)->post(route('platform.communications.templates.store'), [
            'slug'=>'winter_greeting',
            'name'=>'Winter greeting',
            'subject'=>'Hi {{user_name}}',
            'body_markdown'=>'Hello **{{user_name}}** from {{platform_name}}.',
        ])->assertRedirect();

        $template=CommunicationTemplate::where('event_key','custom.winter_greeting')->firstOrFail();
        $this->assertTrue($template->is_active);
        $this->assertDatabaseMissing('communication_rules', [
            'event_key'=>'custom.winter_greeting',
        ]);
        $this->actingAs($admin)->get(route('platform.communications.templates.edit',$template))
            ->assertOk()->assertSee('Winter greeting');
        $this->actingAs($admin)->put(route('platform.communications.templates.update',$template), [
            'name' => 'Winter greeting revised',
            'subject' => 'Hi {{user_name}}',
            'body_markdown' => 'Updated template for {{user_email}}.',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertContains('user_email', $template->fresh()->allowed_variables);
        $this->actingAs($admin)->get(route('platform.communications.templates.edit',$template))
            ->assertOk()->assertSee('Winter greeting revised');


        $this->actingAs($admin)->post(route('platform.communications.templates.preview'),[
            'event_key'=>'custom.winter_greeting',
            'subject'=>'Hello {{user_name}}',
            'body_markdown'=>'Welcome {{user_name}}!',
        ])->assertOk()->assertSee('{{user_name}}');

        $this->actingAs($admin)->post(route('platform.communications.templates.store'), [
            'slug'=>'winter_greeting',
            'name'=>'Duplicate',
            'subject'=>'Duplicate',
            'body_markdown'=>'Duplicate',
        ])->assertSessionHasErrors('slug');
    }
}

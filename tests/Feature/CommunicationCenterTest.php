<?php

namespace Tests\Feature;

use App\Jobs\SendCommunicationDelivery;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CommunicationCenter;
use App\Services\CommunicationTemplateRenderer;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationCenterTest extends TestCase
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

    private function business(): array
    {
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Communication Demo']);
        return [$tenant, $owner];
    }

    private function vars(Tenant $tenant): array
    {
        return [
            'platform_name' => 'POS Cloud',
            'business_name' => $tenant->name,
            'owner_name' => 'Owner',
            'owner_email' => 'owner@example.test',
            'business_url' => route('business.dashboard', $tenant),
        ];
    }

    public function test_migrations_seed_all_templates_and_rules_without_enabling_outbound_email(): void
    {
        foreach (array_keys(config('communications.events')) as $event) {
            $this->assertDatabaseHas('communication_templates', ['event_key' => $event]);
            $this->assertDatabaseHas('communication_rules', [
                'event_key' => $event, 'is_enabled' => true,
                'send_email' => false, 'send_in_app' => true,
            ]);
        }
        $this->assertSame('0', PlatformSetting::valueFor('communications.email_enabled', '0'));
        $this->assertSame('1', PlatformSetting::valueFor('communications.test_mode', '1'));
    }

    public function test_only_permanent_super_admin_can_edit_templates_and_email_configuration(): void
    {
        $template = CommunicationTemplate::where('event_key', 'business.created')->firstOrFail();

        $this->actingAs($this->admin('support'))->get(route('platform.communications.templates.index'))
            ->assertForbidden();
        $this->actingAs($this->admin('operations'))->get(route('platform.communications.settings.index'))
            ->assertForbidden();
        $this->actingAs($this->admin('super_admin', true))->get(route('platform.communications.templates.index'))
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('platform.communications.templates.index'))
            ->assertOk()->assertSee('New business registered');

        $this->actingAs($admin)->put(route('platform.communications.templates.update', $template), [
            'name' => 'Onboarding Welcome',
            'subject' => 'Welcome {{business_name}}',
            'body_markdown' => 'Hello **{{owner_name}}**, welcome to {{platform_name}}.',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('Onboarding Welcome', $template->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communication.template.updated']);
    }

    public function test_template_editor_rejects_unknown_placeholders_and_preview_preserves_variables(): void
    {
        $admin = $this->admin();
        $template = CommunicationTemplate::where('event_key', 'business.created')->firstOrFail();

        $this->actingAs($admin)->put(route('platform.communications.templates.update', $template), [
            'name' => $template->name, 'subject' => 'Welcome',
            'body_markdown' => 'Unapproved {{secret_password}}',
            'is_active' => '1',
        ])->assertSessionHasErrors('body_markdown');

        $this->actingAs($admin)->post(route('platform.communications.templates.preview'), [
            'event_key' => 'business.created',
            'subject' => 'Welcome {{business_name}}',
            'body_markdown' => "Hello **{{owner_name}}**\n\n<script>alert(1)</script>",
        ])->assertOk()->assertSee('Hello')->assertSee('{{owner_name}}')
            ->assertDontSee('<script>', false);
    }

    public function test_markdown_renderer_escapes_customer_values_and_rejects_unsafe_links(): void
    {
        $template = new CommunicationTemplate([
            'subject' => 'Hello {{business_name}}',
            'body_markdown' => "**{{business_name}}**\n\n[Visit](javascript:alert(1))",
        ]);
        $renderer = app(CommunicationTemplateRenderer::class);
        $html = $renderer->html($template, ['business_name' => '<img src=x onerror=alert(1)>']);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_communication_event_creates_only_scoped_inbox_items_with_idempotency(): void
    {
        [$tenant, $owner] = $this->business();
        $admin = $this->admin('operations');
        $outsider = $this->admin('billing');
        $center = app(CommunicationCenter::class);

        $center->emit('business.created', $tenant, $owner, $this->vars($tenant), 'demo-tenant-1001');
        $center->emit('business.created', $tenant, $owner, $this->vars($tenant), 'demo-tenant-1001');

        $this->assertSame(1, UserNotification::where('recipient_user_id', $owner->id)->count());
        $this->assertSame(1, UserNotification::where('recipient_user_id', $admin->id)->count());
        $this->assertSame(0, UserNotification::where('recipient_user_id', $outsider->id)->count());
        $this->assertDatabaseCount('communication_deliveries', 0);

        $note = UserNotification::where('recipient_user_id', $owner->id)->firstOrFail();
        $this->actingAs($outsider)->post(route('platform.notifications.read', $note))
            ->assertNotFound();
        $this->assertNull($note->fresh()->read_at);

        $this->actingAs($owner)->get(route('business.notifications.index', $tenant))
            ->assertOk()->assertSee('Welcome');
        $this->actingAs($owner)->post(route('business.notifications.read', [$tenant, $note]))
            ->assertRedirect();
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_business_inbox_cannot_read_another_business_notification(): void
    {
        [$first, $owner] = $this->business();
        $second = app(CreateTenant::class)->handle($owner, ['name' => 'Second Business']);
        $center = app(CommunicationCenter::class);
        $center->emit('business.created', $first, $owner, $this->vars($first), 'business-one');
        $note = UserNotification::where('recipient_user_id', $owner->id)
            ->where('tenant_id', $first->id)->firstOrFail();

        $this->actingAs($owner)->post(route('business.notifications.read', [$second, $note]))
            ->assertNotFound();
        $this->assertNull($note->fresh()->read_at);

        $this->actingAs($owner)->get(route('business.notifications.index', $second))
            ->assertDontSee('Welcome to POS Cloud');
    }

    public function test_test_mode_redirects_email_to_allowlisted_recipients_and_deduplicates(): void
    {
        Queue::fake();
        [$tenant, $owner] = $this->business();
        CommunicationRule::where('event_key', 'business.created')->update(['send_email' => true]);
        PlatformSetting::updateOrCreate(['key' => 'communications.email_enabled'], ['value' => '1']);
        PlatformSetting::updateOrCreate(['key' => 'communications.test_mode'], ['value' => '1']);
        PlatformSetting::updateOrCreate([
            'key' => 'communications.test_recipients',
        ], ['value' => 'qa@example.test']);

        $center = app(CommunicationCenter::class);
        $center->emit('business.created', $tenant, $owner, $this->vars($tenant), 'invoice-notification-a');
        $center->emit('business.created', $tenant, $owner, $this->vars($tenant), 'invoice-notification-a');

        $deliveries = CommunicationDelivery::all();
        $this->assertGreaterThanOrEqual(1, $deliveries->count());
        foreach ($deliveries as $delivery) {
            $this->assertSame('qa@example.test', $delivery->recipient_email);
            $this->assertStringStartsWith('[TEST]', $delivery->subject);
            $this->assertNotSame($owner->email, $delivery->recipient_email);
            $this->assertSame('queued', $delivery->status);
            $this->assertNotEmpty($delivery->body_html);
        }
        $this->assertSame($deliveries->count(),
            CommunicationDelivery::where('recipient_email', 'qa@example.test')->count());
    }

    public function test_email_settings_require_allowlist_and_reject_live_log_transport(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('platform.communications.settings.update'), [
            'email_enabled' => '1', 'test_mode' => '1', 'test_recipients' => '',
        ])->assertSessionHasErrors('test_recipients');

        $this->actingAs($admin)->put(route('platform.communications.settings.update'), [
            'email_enabled' => '1', 'test_mode' => '0', 'test_recipients' => '',
        ])->assertSessionHasErrors('email_enabled');

        $this->actingAs($admin)->put(route('platform.communications.settings.update'), [
            'email_enabled' => '1', 'test_mode' => '1',
            'test_recipients' => 'qa@example.test',
        ])->assertRedirect();

        $this->assertSame('1', PlatformSetting::valueFor('communications.email_enabled'));
        $this->assertSame('1', PlatformSetting::valueFor('communications.test_mode'));
    }

    public function test_admin_test_mail_is_restricted_to_test_allowlist(): void
    {
        Mail::fake();
        $admin = $this->admin();
        PlatformSetting::updateOrCreate([
            'key' => 'communications.test_recipients',
        ], ['value' => 'qa@example.test']);

        $this->actingAs($admin)->post(route('platform.communications.test'), [
            'recipient' => 'outsider@example.test',
        ])->assertSessionHasErrors('recipient');

        $this->actingAs($admin)->post(route('platform.communications.test'), [
            'recipient' => 'qa@example.test',
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'communication.smtp.tested']);
    }

    public function test_rule_validation_and_user_opt_out_leave_security_alerts_in_app(): void
    {
        $admin = $this->admin();
        $rule = CommunicationRule::where('event_key', 'business.created')->firstOrFail();

        $this->actingAs($admin)->put(route('platform.communications.rules.update', $rule), [
            'is_enabled' => '1', 'send_email' => '0','send_in_app' => '1',
            'to_owner' => '1', 'to_actor' => '0', 'to_platform' => '0',
            'extra_emails' => 'not-an-email-address',
        ])->assertSessionHasErrors('extra_emails');

        $this->actingAs($admin)->put(route('platform.notifications.preferences'), [
            'event_key' => 'security.alert',
            'email_enabled' => '0', 'in_app_enabled' => '0',
        ])->assertRedirect();
        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $admin->id, 'event_key' => 'security.alert',
            'in_app_enabled' => true,
        ]);
    }

    public function test_expiry_reminders_are_duplicate_safe_and_do_not_send_email_by_default(): void
    {
        [$tenant, $owner] = $this->business();
        $tenant->trial()->update([
            'ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);

        $this->assertSame(0, Artisan::call('communications:send-reminders', ['--days' => 3]));
        $this->assertSame(0, Artisan::call('communications:send-reminders', ['--days' => 3]));
        $this->assertSame(1, UserNotification::where('recipient_user_id', $owner->id)
            ->where('event_key', 'trial.ending')->count());
        $this->assertSame(0, CommunicationDelivery::count());
    }

    public function test_mail_worker_rechecks_kill_switch_before_accepting_delivery(): void
    {
        $delivery = CommunicationDelivery::create([
            'event_key' => 'business.created',
            'recipient_email' => 'qa@example.test',
            'subject' => 'Important notification',
            'body_html' => '<p>Safe message</p>',
            'status' => 'queued',
            'dedupe_key' => hash('sha256', 'job-1'),
            'queued_at' => now(),
        ]);
        $job = new SendCommunicationDelivery($delivery->id);
        $job->handle();
        $this->assertSame('skipped', $delivery->fresh()->status);
    }
}

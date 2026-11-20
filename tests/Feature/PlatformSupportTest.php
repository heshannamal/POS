<?php

namespace Tests\Feature;

use App\Models\PlatformAnnouncement;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSupportTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusiness(): array
    {
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, ['name' => 'Business Support Test']);
        return [$tenant, $owner];
    }

    public function test_customer_can_open_ticket_and_platform_can_reply_with_private_notes(): void
    {
        [$tenant, $owner] = $this->setupBusiness();
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => 'support',
        ]);

        $this->actingAs($owner)->get(route('business.support.index', $tenant))->assertOk();
        $this->actingAs($owner)->post(route('business.support.store', $tenant), [
            'subject' => 'Cannot invite new staff', 'category' => 'technical',
            'message' => 'I cannot invite a new cashier. Please advise.',
        ])->assertRedirect();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($tenant->id, $ticket->tenant_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->messages()->count());

        $this->actingAs($admin)->get(route('platform.support.index'))->assertOk();
        $this->actingAs($admin)->get(route('platform.support.show', $ticket))->assertOk();

        $this->actingAs($admin)->post(route('platform.support.reply', $ticket), [
            'message' => 'Internal service diagnostic', 'is_internal' => '1',
        ])->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);

        $this->actingAs($owner)->get(route('business.support.show', [$tenant, $ticket]))
            ->assertOk()->assertDontSee('Internal service diagnostic');

        $this->actingAs($admin)->post(route('platform.support.reply', $ticket), [
            'message' => 'Please try from the Employees page.',
        ])->assertRedirect();

        $this->assertSame('waiting_customer', $ticket->fresh()->status);
        $this->actingAs($owner)->get(route('business.support.show', [$tenant, $ticket]))
            ->assertSee('Please try from the Employees page.')
            ->assertDontSee('Internal service diagnostic');

        $this->actingAs($admin)->patch(route('platform.support.update', $ticket), [
            'status' => 'resolved', 'priority' => 'high',
        ])->assertRedirect();
        $this->assertSame('resolved', $ticket->fresh()->status);

        $this->actingAs($owner)->post(route('business.support.reply', [$tenant, $ticket]), [
            'message' => 'Thank you, the issue is solved.',
        ])->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'support.ticket.replied']);
    }

    public function test_ticket_cannot_be_read_or_replied_to_from_a_different_business(): void
    {
        [$tenant, $owner] = $this->setupBusiness();
        $ticket = SupportTicket::create([
            'tenant_id' => $tenant->id, 'created_by' => $owner->id,
            'subject' => 'Private request', 'category' => 'account',
            'status' => 'open', 'priority' => 'normal',
        ]);
        $otherOwner = User::factory()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, ['name' => 'Another Tenant']);

        $this->actingAs($otherOwner)->get(route('business.support.show', [$other, $ticket]))
            ->assertNotFound();
        $this->actingAs($otherOwner)->post(route('business.support.reply', [$other, $ticket]), [
            'message' => 'A message from another tenant.',
        ])->assertNotFound();
        $this->assertSame(0, $ticket->messages()->count());
    }

    public function test_platform_announcements_are_scheduled_published_and_escaped(): void
    {
        [$tenant, $owner] = $this->setupBusiness();
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => 'operations',
        ]);

        $this->actingAs($admin)->post(route('platform.announcements.store'), [
            'title' => 'Maintenance notice',
            'message' => '<script>alert("unsafe")</script> Maintenance tonight.',
            'tone' => 'warning', 'is_published' => '1',
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $announcement = PlatformAnnouncement::firstOrFail();
        $this->actingAs($owner)->get(route('business.dashboard', $tenant))
            ->assertOk()->assertSee('Maintenance notice')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("unsafe")</script>', false);

        $this->actingAs($admin)->put(route('platform.announcements.update', $announcement), [
            'title' => 'Maintenance notice', 'message' => 'Updated content',
            'tone' => 'info', 'is_published' => '0',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('business.dashboard', $tenant))
            ->assertDontSee('Maintenance notice');
    }

    public function test_audit_and_reports_show_real_events_not_generated_financial_totals(): void
    {
        [$tenant] = $this->setupBusiness();
        $auditor = User::factory()->create([
            'is_platform_admin' => true, 'platform_role' => 'auditor',
        ]);

        $this->actingAs($auditor)->get(route('platform.reports.index'))
            ->assertOk()->assertSee('Business registrations');
        $this->actingAs($auditor)->get(route('platform.audit.index'))
            ->assertOk()->assertSee('tenant.created');
        $this->actingAs($auditor)->get(route('platform.audit.export'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($auditor)->post(route('platform.announcements.store'), [
            'title' => 'Bad announcement', 'message' => 'Blocked', 'tone' => 'info',
            'is_published' => '1',
        ])->assertForbidden();
    }

    public function test_suspended_business_cannot_use_paid_features_but_can_open_support(): void
    {
        [$tenant, $owner] = $this->setupBusiness();
        $operations = User::factory()->create(['is_platform_admin' => true, 'platform_role' => 'operations']);

        $this->actingAs($operations)->patch(route('platform.tenants.status', $tenant), [
            'status' => 'suspended', 'reason' => 'Manual compliance review',
        ])->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->actingAs($owner)->get(route('business.employees.index', $tenant))->assertForbidden();
        $this->actingAs($owner)->get(route('business.support.index', $tenant))->assertOk();
    }
}

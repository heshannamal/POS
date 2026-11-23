<?php

namespace App\Services;

use App\Jobs\SendCommunicationDelivery;
use App\Models\AuditLog;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\PlatformSetting;
use App\Models\SecurityAlert;
use App\Models\SubscriptionPaymentRequest;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommunicationCenter
{
    public function eventFromAudit(AuditLog $audit): void
    {
        $map = [
            'tenant.created' => 'business.created',
            'billing.invoice.created' => 'billing.invoice_issued',
            'billing.refund.recorded' => 'billing.refund_recorded',
            'subscription.payment.submitted' => 'payment.submitted',
            'subscription.payment.approved' => 'payment.approved',
            'subscription.payment.rejected' => 'payment.rejected',
            'support.ticket.created' => 'support.opened',
            'support.customer.replied' => 'support.customer_replied',
            'support.ticket.replied' => 'support.replied',
            'employee.invited' => 'staff.invited',
        ];
        $event = $map[$audit->action] ?? null;
        if (! $event || ! $audit->tenant_id) return;

        $tenant = Tenant::find($audit->tenant_id);
        if (! $tenant) return;
        $variables = [
            'platform_name' => config('app.name', 'POS Cloud'),
            'business_name' => $tenant->name,
            'owner_name' => '',
            'owner_email' => $tenant->contact_email ?? '',
            'business_url' => route('business.dashboard', $tenant),
            'payment_id' => '',
            'payment_amount' => '',
            'currency' => $tenant->currency,
            'payment_url' => route('business.subscription.index', $tenant),
            'ticket_id' => '',
            'ticket_subject' => '',
            'ticket_url' => route('business.support.index', $tenant),
            'trial_end_date' => $tenant->trial?->ends_at?->format('Y-m-d') ?? '',
        ];
        if (str_starts_with($event, 'billing.') && $audit->subject_id) {
            $invoiceId = $event === 'billing.refund_recorded'
                ? (int) ($audit->metadata['invoice_id'] ?? 0)
                : $audit->subject_id;
            $invoice = \App\Models\BillingInvoice::find($invoiceId);
            if ($invoice && $invoice->tenant_id === $tenant->id) {
                $variables['invoice_number'] = $invoice->number;
                $variables['invoice_amount'] = \App\Services\BillingMoney::format($invoice->total_minor);
                $variables['currency'] = $invoice->currency;
                $variables['invoice_url'] = route('business.billing.show',[$tenant,$invoice]);
            }
        }
        if (str_starts_with($event, 'payment.') && $audit->subject_id) {
            $payment = SubscriptionPaymentRequest::find($audit->subject_id);
            if ($payment && $payment->tenant_id === $tenant->id) {
                $variables['payment_id'] = (string) $payment->id;
                $variables['payment_amount'] = number_format($payment->requested_amount_minor / 100, 2);
                $variables['currency'] = $payment->currency;
                if ($event === 'payment.submitted') {
                    $variables['payment_url'] = route('platform.payments.review.show', $payment);
                }
            }
        }
        if (str_starts_with($event, 'support.') && $audit->subject_id) {
            $ticket = SupportTicket::find($audit->subject_id);
            if ($ticket && $ticket->tenant_id === $tenant->id) {
                $variables['ticket_id'] = (string) $ticket->id;
                $variables['ticket_subject'] = $ticket->subject;
                $variables['ticket_url'] = in_array($event, ['support.opened','support.customer_replied'], true)
                    ? route('platform.support.show', $ticket)
                    : route('business.support.show', [$tenant, $ticket]);
            }
        }
        $owner = $tenant->users()->wherePivot('is_owner', true)->first();
        $variables['owner_name'] = $owner?->name ?? '';

        $this->emit($event, $tenant, $audit->actor, $variables, 'audit-'.$audit->id);
    }

    public function securityAlert(SecurityAlert $alert): void
    {
        $this->emit('security.alert', null, null, [
            'platform_name' => config('app.name', 'POS Cloud'),
            'alert_id' => (string) $alert->id,
            'alert_type' => str_replace('_', ' ', $alert->type),
            'security_url' => route('platform.security.index'),
            'business_name' => 'Platform Security',
        ], 'security-alert-'.$alert->id);
    }

    /**
     * Dedupe is per event source, recipient and channel. Template variables
     * are escaped and remain data rather than executable Blade/PHP.
     */
    public function emit(string $event, ?Tenant $tenant, ?User $actor, array $vars, string $sourceKey): void
    {
        if (! isset(config('communications.events')[$event])) return;
        $rule = CommunicationRule::where('event_key', $event)->first();
        $template = CommunicationTemplate::where('event_key', $event)->first();
        if (! $rule?->is_enabled || ! $template?->is_active) return;

        $recipients = collect();
        if ($tenant && $rule->to_owner) {
            $recipients = $recipients->merge($tenant->users()->wherePivot('is_owner', true)
                ->wherePivot('status', 'active')->get());
        }
        if ($actor && $rule->to_actor) $recipients->push($actor);
        if ($rule->to_platform) {
            $roles = (config('communications.events', [])[$event]['platform_roles'] ?? []);
            $recipients = $recipients->merge(User::query()
                ->where('is_platform_admin', true)
                ->whereNull('temporary_admin_expires_at')
                ->where(fn ($q) => $q->whereNull('platform_role')
                    ->orWhere('platform_role', 'super_admin')
                    ->orWhereIn('platform_role', $roles))->get());
        }

        $recipients = $recipients->unique('id')->filter(fn (User $user) => $user->hasVerifiedEmail());
        $renderer = app(CommunicationTemplateRenderer::class);
        $subject = $renderer->subject($template, $vars);
        $html = $renderer->html($template, $vars);
        $path = $this->actionUrl($event, $tenant, $vars);
        $message = trim(strip_tags($html));
        $globalEmail = PlatformSetting::valueFor('communications.email_enabled', '0') === '1';
        $testMode = PlatformSetting::valueFor('communications.test_mode', '1') === '1';
        $testEmails = array_values(array_filter(array_map('trim', explode(',',
            PlatformSetting::valueFor('communications.test_recipients', '')))));

        foreach ($recipients as $recipient) {
            $targetUrl = $path;
            if ($recipient->is_platform_admin && $tenant && $event === 'business.created') {
                $targetUrl = route('platform.tenants.show', $tenant);
            }
            $preference = UserNotificationPreference::where('user_id', $recipient->id)
                ->where('event_key', $event)->first();
            $key = hash('sha256', $sourceKey.'|'.$event.'|'.$recipient->id);

            if ($rule->send_in_app && ($event === 'security.alert' || ($preference?->in_app_enabled ?? true))) {
                UserNotification::firstOrCreate([
                    'recipient_user_id' => $recipient->id, 'dedupe_key' => $key,
                ], [
                    'tenant_id' => $tenant?->id, 'event_key' => $event,
                    'title' => $subject, 'message' => mb_substr($message, 0, 2000),
                    'action_url' => $targetUrl,
                ]);
            }

            if ($rule->send_email && $globalEmail && ($preference?->email_enabled ?? true)) {
                $targets = $testMode ? $testEmails : [$recipient->email];
                foreach ($targets as $email) {
                    $this->queue($event, $tenant, $recipient, $email, $subject, $html, $key, $testMode);
                }
            }
        }

        // Extra addresses are email-only and never become user accounts.
        if ($rule->send_email && $globalEmail) {
            foreach ($rule->extra_emails ?? [] as $email) {
                foreach ($testMode ? $testEmails : [$email] as $target) {
                    $this->queue($event, $tenant, null, $target, $subject, $html,
                        hash('sha256', $sourceKey.'|'.$event.'|extra|'.$email), $testMode, $email);
                }
            }
        }
    }

    private function queue(
        string $event, ?Tenant $tenant, ?User $user, string $address,
        string $subject, string $html, string $key, bool $testMode,
        ?string $origin = null
    ): void {
        $delivery = CommunicationDelivery::firstOrCreate([
            'recipient_email' => $address,
            'dedupe_key' => $key,
        ], [
            'event_key' => $event,
            'tenant_id' => $tenant?->id,
            'recipient_user_id' => $user?->id,
            'original_email' => $testMode ? ($origin ?? $user?->email) : null,
            'subject' => $testMode ? '[TEST] '.$subject : $subject,
            'body_html' => $html,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        if ($delivery->wasRecentlyCreated) {
            SendCommunicationDelivery::dispatch($delivery->id)->afterCommit();
        }
    }

    private function actionUrl(string $event, ?Tenant $tenant, array $vars): ?string
    {
        return match ($event) {
            'billing.invoice_issued','billing.refund_recorded' => $vars['invoice_url'] ?? null,
            'payment.submitted', 'payment.approved', 'payment.rejected' => $vars['payment_url'] ?? null,
            'support.opened', 'support.replied', 'support.customer_replied' => $vars['ticket_url'] ?? null,
            'security.alert' => route('platform.security.index'),
            default => $tenant ? route('business.dashboard', $tenant) : null,
        };
    }
}

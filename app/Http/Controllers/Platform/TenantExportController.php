<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantExportController extends Controller
{
    /**
     * Portable tenant snapshot, not a physical database backup.
     * Internal platform notes, password hashes, tokens, private upload paths,
     * confidential administrator-only support notes and secrets are excluded.
     */
    public function download(Request $request, Tenant $tenant): StreamedResponse
    {
        $request->validate(['admin_password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('admin_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['admin_password' => 'Administrator password is incorrect.']);
        }

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'actor_id' => $request->user()->id,
            'action' => 'tenant.export.downloaded',
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'metadata' => ['format' => 'json', 'type' => 'portable_snapshot'],
            'ip_address' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($tenant): void {
            $tenant->load(['trial', 'branches', 'subscription.plan', 'users', 'moduleGrants.module']);
            $payload = [
                'format' => 'pos-cloud-tenant-snapshot-v1',
                'exported_at' => now()->toIso8601String(),
                'business' => $tenant->only([
                    'id', 'name', 'slug', 'contact_email', 'contact_person', 'phone',
                    'address', 'country', 'website', 'registration_number',
                    'currency', 'timezone', 'status', 'onboarding_status',
                    'onboarding_completed_at', 'created_at',
                ]),
                'branches' => $tenant->branches->map(fn ($branch) => $branch->only([
                    'id', 'name', 'code', 'phone', 'address', 'is_active', 'is_default',
                ]))->all(),
                'members' => $tenant->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_owner' => (bool) $user->pivot->is_owner,
                    'membership_status' => $user->pivot->status,
                ])->all(),
                'trial' => $tenant->trial?->only([
                    'started_at', 'ends_at', 'duration_days', 'extension_days',
                    'is_active', 'converted_at',
                ]),
                'subscription' => $tenant->subscription ? [
                    'plan' => $tenant->subscription->plan?->name,
                    'status' => $tenant->subscription->status,
                    'source' => $tenant->subscription->source,
                    'started_at' => $tenant->subscription->started_at,
                    'ends_at' => $tenant->subscription->ends_at,
                ] : null,
                'modules' => $tenant->moduleGrants->map(fn ($grant) => [
                    'module' => $grant->module?->code,
                    'enabled' => (bool) $grant->enabled,
                    'expires_at' => $grant->expires_at,
                ])->all(),
                'payment_requests' => $tenant->paymentRequests()->orderBy('id')->get()->map(fn ($payment) => [
                    'id' => $payment->id,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'currency' => $payment->currency,
                    'requested_amount_minor' => $payment->requested_amount_minor,
                    'approved_amount_minor' => $payment->approved_amount_minor,
                    'billing_cycle' => $payment->billing_cycle,
                    'created_at' => $payment->created_at,
                    'reviewed_at' => $payment->reviewed_at,
                ])->all(),
                'support_tickets' => $tenant->supportTickets()
                    ->orderBy('id')->get()->map(fn ($ticket) => [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'category' => $ticket->category,
                        'status' => $ticket->status,
                        'created_at' => $ticket->created_at,
                        'messages' => $ticket->messages()->where('is_internal', false)
                            ->orderBy('id')->get()->map(fn ($message) => [
                                'message' => $message->message,
                                'created_at' => $message->created_at,
                            ])->all(),
                    ])->all(),
            ];

            echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'pos-tenant-'.$tenant->slug.'-'.now()->format('Ymd-His').'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

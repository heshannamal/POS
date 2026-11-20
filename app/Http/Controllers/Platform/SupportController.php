<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    private const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];

    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with(['tenant:id,name,slug', 'creator:id,name,email', 'assignee:id,name'])
            ->latest('id');

        if ($status = $request->query('status')) {
            if (in_array($status, self::STATUSES, true)) {
                $query->where('status', $status);
            }
        }
        if ($priority = $request->query('priority')) {
            if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                $query->where('priority', $priority);
            }
        }
        if ($term = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('subject', 'like', '%'.$term.'%')
                ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$term.'%')));
        }
        $tickets = $query->paginate(20)->withQueryString();
        $stats = [
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'waiting' => SupportTicket::where('status', 'waiting_customer')->count(),
            'resolved' => SupportTicket::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return view('platform.support.index', compact('tickets', 'stats'));
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['tenant', 'creator', 'assignee', 'messages.sender']);
        $assignees = User::where('is_platform_admin', true)
            ->where(fn ($q) => $q->whereNull('platform_role')
                ->orWhereIn('platform_role', ['super_admin', 'operations', 'support']))
            ->orderBy('name')->get(['id', 'name', 'email']);

        return view('platform.support.show', compact('ticket', 'assignees'));
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_platform_admin', true)],
        ]);

        DB::transaction(function () use ($request, $ticket, $data) {
            if (! empty($data['assigned_to'])) {
                $assignee = User::findOrFail($data['assigned_to']);
                abort_unless(in_array($assignee->platformRole(), ['super_admin', 'operations', 'support'], true)
                    && ! $assignee->isTemporaryAdminExpired(), 422);
            }
            $ticket->update([
                'status' => $data['status'], 'priority' => $data['priority'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
            ]);
            AuditLog::create([
                'tenant_id' => $ticket->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'support.ticket.updated', 'subject_type' => SupportTicket::class,
                'subject_id' => $ticket->id,
                'metadata' => ['status' => $data['status'], 'priority' => $data['priority']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Support ticket updated.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $ticket, $data) {
            SupportMessage::create([
                'ticket_id' => $ticket->id, 'sender_id' => $request->user()->id,
                'message' => $data['message'], 'is_internal' => (bool) ($data['is_internal'] ?? false),
            ]);
            if (! ($data['is_internal'] ?? false)) {
                $ticket->update(['status' => 'waiting_customer', 'resolved_at' => null]);
            }
            AuditLog::create([
                'tenant_id' => $ticket->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'support.ticket.replied',
                'subject_type' => SupportTicket::class, 'subject_id' => $ticket->id,
                'metadata' => ['internal_note' => (bool) ($data['is_internal'] ?? false)],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Response saved.');
    }
}

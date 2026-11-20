<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $tickets = SupportTicket::forTenant($tenant)->latest('id')->paginate(12);
        return view('business.support.index', compact('tenant', 'tickets'));
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['general', 'technical', 'billing', 'account'])],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($tenant, $data, $request) {
            $ticket = SupportTicket::create([
                'tenant_id' => $tenant->id, 'created_by' => $request->user()->id,
                'subject' => $data['subject'], 'category' => $data['category'],
                'status' => 'open', 'priority' => 'normal',
            ]);
            SupportMessage::create([
                'ticket_id' => $ticket->id, 'sender_id' => $request->user()->id,
                'message' => $data['message'], 'is_internal' => false,
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'support.ticket.created', 'subject_type' => SupportTicket::class,
                'subject_id' => $ticket->id, 'ip_address' => $request->ip(),
            ]);
            return $ticket;
        });

        return redirect()->route('business.support.show', [$tenant, $ticket])
            ->with('status', 'Support request submitted.');
    }

    public function show(Tenant $tenant, SupportTicket $ticket): View
    {
        abort_unless($ticket->tenant_id === $tenant->id, 404);
        $ticket->load('creator');
        $messages = $ticket->messages()->where('is_internal', false)
            ->with('sender:id,name')->oldest('id')->get();

        return view('business.support.show', compact('tenant', 'ticket', 'messages'));
    }

    public function reply(Request $request, Tenant $tenant, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $ticket, $data) {
            SupportMessage::create([
                'ticket_id' => $ticket->id, 'sender_id' => $request->user()->id,
                'message' => $data['message'], 'is_internal' => false,
            ]);
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'support.customer.replied',
                'subject_type' => SupportTicket::class, 'subject_id' => $ticket->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Reply added.');
    }
}

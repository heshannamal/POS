<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantNotesController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $note = $tenant->notes()->create([
                'body' => $data['body'],
                'author_id' => $request->user()->id,
            ]);

            // Note text is never copied into the general audit log.
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.note.created',
                'subject_type' => TenantNote::class, 'subject_id' => $note->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Private administrator note saved.');
    }

    public function destroy(Request $request, Tenant $tenant, TenantNote $note): RedirectResponse
    {
        abort_unless($note->tenant_id === $tenant->id, 404);
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $tenant, $note): void {
            $locked = TenantNote::whereKey($note->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->tenant_id === $tenant->id, 404);
            $id = $locked->id;
            $locked->delete();
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'tenant.note.deleted',
                'subject_type' => TenantNote::class, 'subject_id' => $id,
                'metadata' => ['reason' => $request->input('reason')],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Private note deleted with an audit record.');
    }
}

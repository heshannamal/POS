<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationInboxController extends Controller
{
    public function platform(Request $request): View
    {
        return $this->inbox($request, null);
    }

    public function business(Request $request, Tenant $tenant): View
    {
        return $this->inbox($request, $tenant);
    }

    private function inbox(Request $request, ?Tenant $tenant): View
    {
        $query = UserNotification::where('recipient_user_id', $request->user()->id)
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id));

        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['unread','read'])],
            'event' => ['nullable',Rule::in(array_keys(config('communications.events',[])))],
        ]);
        if (($filter['status'] ?? '') === 'unread') $query->whereNull('read_at');
        if (($filter['status'] ?? '') === 'read') $query->whereNotNull('read_at');
        if ($filter['event'] ?? null) $query->where('event_key', $filter['event']);

        $notifications = $query->latest('id')->paginate(20)->withQueryString();
        $unreadCount = UserNotification::where('recipient_user_id',$request->user()->id)
            ->when($tenant,fn($q)=>$q->where('tenant_id',$tenant->id))
            ->whereNull('read_at')->count();
        $preferences = UserNotificationPreference::where('user_id',$request->user()->id)
            ->get()->keyBy('event_key');

        return view('notifications.index',compact('notifications','unreadCount','preferences','tenant'));
    }

    public function markPlatform(Request $request, UserNotification $notification): RedirectResponse
    {
        return $this->mark($request,$notification);
    }

    public function markBusiness(Request $request, Tenant $tenant, UserNotification $notification): RedirectResponse
    {
        return $this->mark($request,$notification,$tenant);
    }

    private function mark(Request $request, UserNotification $notification, ?Tenant $tenant = null): RedirectResponse
    {
        abort_unless($notification->recipient_user_id === $request->user()->id,404);
        if ($tenant) abort_unless($notification->tenant_id === $tenant->id,404);

        if (! $notification->read_at) $notification->update(['read_at'=>now()]);
        return back()->with('status','Notification marked as read.');
    }

    public function markAllPlatform(Request $request): RedirectResponse
    {
        return $this->markAll($request);
    }

    public function markAllBusiness(Request $request, Tenant $tenant): RedirectResponse
    {
        return $this->markAll($request,$tenant);
    }

    private function markAll(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        UserNotification::where('recipient_user_id',$request->user()->id)
            ->when($tenant,fn($q)=>$q->where('tenant_id',$tenant->id))
            ->whereNull('read_at')->update(['read_at'=>now()]);
        return back()->with('status','All notifications marked as read.');
    }

    public function preferencesPlatform(Request $request): RedirectResponse
    {
        return $this->preferences($request);
    }

    public function preferencesBusiness(Request $request, Tenant $tenant): RedirectResponse
    {
        return $this->preferences($request, $tenant);
    }

    private function preferences(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        $data = $request->validate([
            'event_key'=>['required',Rule::in(array_keys(config('communications.events',[])))],
            'email_enabled'=>['required','boolean'],
            'in_app_enabled'=>['required','boolean'],
        ]);

        // Critical security notices must stay visible in the admin console.
        if ($data['event_key'] === 'security.alert') $data['in_app_enabled'] = true;

        DB::transaction(function () use ($request,$tenant,$data): void {
            UserNotificationPreference::updateOrCreate([
                'user_id'=>$request->user()->id,
                'event_key'=>$data['event_key'],
            ], [
                'email_enabled'=>(bool)$data['email_enabled'],
                'in_app_enabled'=>(bool)$data['in_app_enabled'],
            ]);

            AuditLog::create([
                'tenant_id'=>$tenant?->id,'actor_id'=>$request->user()->id,
                'action'=>'communication.preferences.updated',
                'metadata'=>['event'=>$data['event_key']],
                'ip_address'=>$request->ip(),
            ]);
        });
        return back()->with('status','Your notification preferences were saved.');
    }
}

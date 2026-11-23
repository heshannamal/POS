<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserSecuritySession;
use App\Services\UserSessionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request, UserSessionTracker $tracker): View
    {
        $user = $request->user();
        $sessions = $user->securitySessions()->latest('last_seen_at')->paginate(20);
        $currentHash = $tracker->hash($request);
        return view('security.sessions', compact('sessions', 'currentHash'));
    }

    public function revoke(Request $request, UserSecuritySession $session, UserSessionTracker $tracker): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 404);
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Your password is incorrect.']);
        }

        $session->update(['revoked_at' => now()]);
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'security.session.revoked',
            'subject_type' => UserSecuritySession::class, 'subject_id' => $session->id,
            'ip_address' => $request->ip(),
        ]);

        if (hash_equals($session->session_hash, $tracker->hash($request))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'Current session revoked.');
        }

        return back()->with('status', 'Selected session revoked.');
    }
}

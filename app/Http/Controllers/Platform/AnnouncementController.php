<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = PlatformAnnouncement::with('author:id,name')
            ->latest()->paginate(15);

        return view('platform.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return view('platform.announcements.form', ['announcement' => null]);
    }

    public function edit(PlatformAnnouncement $announcement): View
    {
        return view('platform.announcements.form', compact('announcement'));
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'tone' => ['required', Rule::in(['info', 'success', 'warning'])],
            'is_published' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $announcement = DB::transaction(function () use ($request, $data) {
            $announcement = PlatformAnnouncement::create(array_merge($data, [
                'created_by' => $request->user()->id,
            ]));
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'announcement.created',
                'subject_type' => PlatformAnnouncement::class, 'subject_id' => $announcement->id,
                'metadata' => ['published' => (bool) $data['is_published']],
                'ip_address' => $request->ip(),
            ]);
            return $announcement;
        });

        return redirect()->route('platform.announcements.edit', $announcement)
            ->with('status', 'Announcement created.');
    }

    public function update(Request $request, PlatformAnnouncement $announcement): RedirectResponse
    {
        $data = $this->data($request);
        DB::transaction(function () use ($request, $announcement, $data) {
            $announcement->update($data);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'announcement.updated',
                'subject_type' => PlatformAnnouncement::class, 'subject_id' => $announcement->id,
                'metadata' => ['published' => (bool) $data['is_published']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Announcement updated.');
    }
}

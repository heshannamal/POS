@extends('layouts.app')
@section('title','Platform announcements')
@section('header','Announcements')
@section('content')
<div class="page-head">
    <div><span class="overline">BUSINESS COMMUNICATION</span><h1>Platform announcements</h1><p class="muted">Publish operational notices to signed-in business dashboards, with scheduling and automatic expiry.</p></div>
    <a class="btn btn-primary" href="{{ route('platform.announcements.create') }}">＋ New announcement</a>
</div>
<section class="panel">
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Announcement</th><th>Tone</th><th>Visibility</th><th>Schedule</th><th>Updated</th><th></th></tr></thead>
        <tbody>
            @forelse($announcements as $announcement)
                <tr>
                    <td><strong>{{ $announcement->title }}</strong><small class="table-subtext">{{ \Illuminate\Support\Str::limit($announcement->message, 110) }}</small></td>
                    <td>{{ ucfirst($announcement->tone) }}</td>
                    <td><span class="status status-{{ $announcement->is_published ? 'active' : 'trial' }}">{{ $announcement->is_published ? 'Published flag' : 'Draft' }}</span></td>
                    <td>{{ $announcement->starts_at?->format('d M Y H:i') ?? 'Immediately' }} — {{ $announcement->ends_at?->format('d M Y H:i') ?? 'No expiry' }}</td>
                    <td>{{ $announcement->updated_at->format('d M Y') }}</td>
                    <td><a class="btn btn-small btn-outline" href="{{ route('platform.announcements.edit', $announcement) }}">Edit →</a></td>
                </tr>
            @empty <tr><td colspan="6" class="empty-row">No announcements. Create a draft to get started.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div class="pagination-line">{{ $announcements->links() }}</div>
</section>
@endsection

@extends('layouts.app')
@section('title','Archived businesses')
@section('header','Archived businesses')
@section('content')
<div class="page-head">
    <div><span class="overline">SAFE DATA RETENTION</span><h1>Archived workspaces</h1><p class="muted">Soft-deleted businesses are hidden from normal login and business screens. Restoration keeps their original account state, users, branches and financial records.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.tenants.index') }}">← Business directory</a>
</div>
<section class="panel">
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Business</th><th>Archived on</th><th>Previous status</th><th>Users / Stores</th><th>Restore</th></tr></thead>
        <tbody>
            @forelse($tenants as $tenant)
                <tr>
                    <td><strong>{{ $tenant->name }}</strong><small class="table-subtext">{{ $tenant->slug }}</small></td>
                    <td>{{ $tenant->deleted_at?->format('d M Y H:i') }}</td>
                    <td><span class="status status-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></td>
                    <td>{{ $tenant->users_count }} users / {{ $tenant->branches_count }} stores</td>
                    <td>
                        <form method="POST" action="{{ route('platform.tenants.restore', $tenant->id) }}" class="tenant-archive-form">
                            @csrf
                            <input name="confirmation" required placeholder="Exact business slug" aria-label="Confirm slug for {{ $tenant->name }}">
                            <input name="reason" required minlength="10" maxlength="1000" placeholder="Restore reason">
                            <input name="admin_password" type="password" autocomplete="current-password" required placeholder="Your admin password">
                            <button class="btn btn-small btn-secondary" type="submit" data-confirm="Restore archived business with its original status?">Restore</button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="5" class="empty-row">No archived businesses.</td></tr> @endforelse
        </tbody></table></div>
    <div class="pagination-line">{{ $tenants->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Enter the exact business slug shown beside each name and your current permanent Super Admin password. There is no permanent-delete action in this release.</div>
@endsection

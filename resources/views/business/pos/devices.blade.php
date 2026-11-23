@extends('layouts.app')
@section('title','Connected Devices')
@section('header','Connected Devices')
@section('content')
<div class="page-head">
    <div>
        <span class="overline">POS CLOUD · APPLICATION SECURITY</span>
        <h1>Desktop & mobile devices</h1>
        <p class="muted">Review application access and revoke lost, unused or unauthorized devices. Revocation prevents future API requests.</p>
    </div>
</div>
<section class="panel">
    <h2>Registered devices</h2>
    <div style="overflow-x:auto">
        <table class="data-table" style="width:100%">
            <thead><tr><th>Device</th><th>Assigned user</th><th>Platform</th><th>Last activity</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($devices as $device)
                <tr>
                    <td><strong>{{ $device->name }}</strong><p class="muted">#{{ $device->id }} · {{ $device->app_version ?: 'Unknown version' }}</p></td>
                    <td>{{ $device->user_name }}<p class="muted">{{ $device->user_email }}</p></td>
                    <td>{{ ucfirst($device->platform) }}</td>
                    <td>{{ $device->last_seen_at ?: 'Never' }}</td>
                    <td>{{ $device->revoked_at ? 'Revoked' : ($device->is_active ? 'Active' : 'Inactive') }}</td>
                    <td>
                    @if($device->is_active && !$device->revoked_at)
                        <form action="{{ route('business.pos.devices.revoke',[$tenant,$device->id]) }}" method="post"
                              onsubmit="return confirm('Revoke this device and invalidate its access token?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-secondary">Revoke access</button>
                        </form>
                    @else
                        —
                    @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No application devices registered yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $devices->links() }}
</section>
@endsection
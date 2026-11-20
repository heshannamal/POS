@extends('layouts.app')
@section('title','Store: '.$store->name)
@section('header','Store management')
@section('content')
@php
    $canManage=in_array(auth()->user()->platformRole(),['super_admin','operations'],true);
    $overrides=$store->settings_overrides ?? [];
@endphp
<div class="page-head">
    <div><span class="overline">STORE DETAILS</span><h1>{{ $store->name }}</h1><p class="muted">{{ $store->tenant->name }} · {{ $store->code }} · {{ $store->is_default ? 'Default branch' : 'Additional branch' }}</p></div>
    <a href="{{ route('platform.stores.index') }}" class="btn btn-outline">← Global stores</a>
</div>
<div class="stat-grid">
    <div class="stat-card tint-lavender"><span class="stat-icon">▦</span><span class="stat-label">Business</span><strong class="stat-text">{{ $store->tenant->name }}</strong><small>{{ $store->tenant->country ?? 'Country not set' }}</small></div>
    <div class="stat-card tint-blue"><span class="stat-icon">♙</span><span class="stat-label">Assigned staff</span><strong>{{ $store->staff->count() }}</strong><small>Saved location assignments</small></div>
    <div class="stat-card tint-mint"><span class="stat-icon">▣</span><span class="stat-label">Registered devices</span><strong>{{ $store->devices->count() }}</strong><small>Not live connections</small></div>
    <div class="stat-card tint-peach"><span class="stat-icon">◷</span><span class="stat-label">Status</span><strong class="stat-text">{{ ucfirst($store->status) }}</strong><small>{{ $store->timezone ?: $store->tenant->timezone }}</small></div>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Store profile & manager</h2><p class="muted">The selected manager must already have an active membership and owner/manager role in this business.</p></div></div>
        @if($canManage)
            <form method="POST" action="{{ route('platform.stores.update',$store) }}" class="form-stack">
                @csrf @method('PATCH')
                <div class="tenant-fields-grid">
                    <div><label for="store_name">Store name</label><input id="store_name" name="name" maxlength="120" required value="{{ old('name',$store->name) }}"></div>
                    <div><label for="store_phone">Phone</label><input id="store_phone" name="phone" maxlength="30" value="{{ old('phone',$store->phone) }}"></div>
                </div>
                <label for="store_address">Address</label><textarea id="store_address" name="address" maxlength="1000" rows="3">{{ old('address',$store->address) }}</textarea>
                <label for="store_timezone">Store timezone</label>
                <select name="timezone" id="store_timezone">
                    <option value="">Inherit {{ $store->tenant->timezone }}</option>
                    @foreach(timezone_identifiers_list() as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone',$store->timezone)===$timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
                <div class="tenant-fields-grid">
                    <div>
                        <label for="store_status">Status</label>
                        <select id="store_status" name="status" required>
                            <option value="active" @selected($store->status==='active')>Active</option>
                            <option value="closed" @selected($store->status==='closed') @disabled($store->is_default)>Closed</option>
                            <option value="suspended" @selected($store->status==='suspended') @disabled($store->is_default)>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label for="store_manager">Store manager</label>
                        <select id="store_manager" name="manager_user_id">
                            <option value="">No assigned manager</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}" @selected($store->manager_user_id===$manager->id)>{{ $manager->name }} · {{ $manager->pivot->is_owner ? 'Owner' : 'Manager' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label for="store_reason">Internal change reason</label>
                <input id="store_reason" name="reason" maxlength="1000" minlength="5" required placeholder="Reason for store/manager update">
                <button type="submit" class="btn btn-primary" data-confirm="Apply these store settings and log the change?">Save store →</button>
            </form>
        @else
            <dl class="detail-list">
                <div><dt>Phone</dt><dd>{{ $store->phone ?: '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $store->address ?: '—' }}</dd></div>
                <div><dt>Manager</dt><dd>{{ $store->manager?->name ?? 'Unassigned' }}</dd></div>
                <div><dt>Timezone</dt><dd>{{ $store->timezone ?: $store->tenant->timezone }}</dd></div>
            </dl>
        @endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Store configuration overrides</h2><p class="muted">Saved defaults for future POS and inventory modules</p></div></div>
        @if($canManage)
            <form method="POST" action="{{ route('platform.stores.settings',$store) }}" class="form-stack">
                @csrf @method('PUT')
                <label for="receipt_footer">Receipt footer (optional)</label>
                <textarea id="receipt_footer" name="receipt_footer" rows="4" maxlength="500" placeholder="Thank you for visiting">{{ old('receipt_footer',$overrides['receipt_footer'] ?? '') }}</textarea>
                <label for="low_stock_threshold">Low-stock alert threshold</label>
                <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" max="10000" value="{{ old('low_stock_threshold',$overrides['low_stock_threshold'] ?? '') }}">
                <label for="print_receipts">Automatic receipt printing preference</label>
                <select name="print_receipts" id="print_receipts">
                    <option value="1" @selected(old('print_receipts',$overrides['print_receipts'] ?? true)==1)>Enabled preference</option>
                    <option value="0" @selected(old('print_receipts',$overrides['print_receipts'] ?? true)==0)>Disabled preference</option>
                </select>
                <button type="submit" class="btn btn-secondary">Save configuration</button>
            </form>
        @else
            <dl class="detail-list">
                <div><dt>Receipt footer</dt><dd>{{ $overrides['receipt_footer'] ?? 'Inherited' }}</dd></div>
                <div><dt>Low-stock threshold</dt><dd>{{ $overrides['low_stock_threshold'] ?? 'Inherited' }}</dd></div>
                <div><dt>Auto print preference</dt><dd>{{ isset($overrides['print_receipts']) ? ($overrides['print_receipts'] ? 'Enabled' : 'Disabled') : 'Inherited' }}</dd></div>
            </dl>
        @endif
        <div class="hint-box" style="margin-top:16px">These are stored settings only. No printer, stock or transaction integration has been activated.</div>
    </section>
</div>
<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Store team</h2><p class="muted">Assignments apply only to this store, and do not create new business memberships.</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Member</th><th>Email</th><th>Membership</th><th></th></tr></thead>
            <tbody>
                @forelse($store->staff as $employee)
                    <tr>
                        <td><strong>{{ $employee->name }}</strong>@if($store->manager_user_id===$employee->id)<small class="table-subtext">Store manager</small>@endif</td>
                        <td>{{ $employee->email }}</td>
                        <td>{{ $store->tenant->users()->whereKey($employee->id)->first()?->pivot?->status ?? 'Unknown' }}</td>
                        <td>
                            @if($canManage)
                                <form method="POST" action="{{ route('platform.stores.staff.unassign',[$store,$employee]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-small btn-outline" @disabled($store->manager_user_id===$employee->id) data-confirm="Remove store assignment (not the business membership)?">Unassign</button>
                                </form>
                            @else — @endif
                        </td>
                    </tr>
                @empty <tr><td colspan="4" class="empty-row">No staff assigned to this store yet.</td></tr>@endforelse
            </tbody>
        </table></div>
        @if($canManage)
            <form method="POST" action="{{ route('platform.stores.staff.assign',$store) }}" class="form-stack" style="margin-top:20px">
                @csrf
                <label for="assign_member">Assign an active business member</label>
                <select name="user_id" id="assign_member" required>
                    <option value="">Choose a team member...</option>
                    @foreach($eligible as $member)
                        @if(!$store->staff->contains('id',$member->id))
                            <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                        @endif
                    @endforeach
                </select>
                <button class="btn btn-secondary" type="submit" @disabled($store->status!=='active')>Assign member</button>
            </form>
        @endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Registered devices</h2><p class="muted">Terminals, printers and barcode scanners</p></div></div>
        <div class="stack-list">
            @forelse($store->devices as $device)
                <div class="store-device-card">
                    <div class="stack-row"><span class="circle-initial">▣</span><span class="stack-grow"><strong>{{ $device->name }}</strong><small>{{ ucwords(str_replace('_',' ',$device->type)) }} · {{ $device->manufacturer ?? '' }} {{ $device->model ?? '' }}</small><small>Serial: {{ $device->serial_number ?? 'Not entered' }} · Last seen: Never connected</small></span><span class="status status-{{ $device->status==='registered'?'trial':'suspended' }}">{{ ucfirst($device->status) }}</span></div>
                    @if($canManage)
                        <form method="POST" action="{{ route('platform.stores.devices.update',[$store,$device]) }}" class="form-stack">
                            @csrf @method('PATCH')
                            <label for="device_status_{{ $device->id }}">Inventory status</label>
                            <select name="status" id="device_status_{{ $device->id }}">
                                @foreach(['registered'=>'Registered','inactive'=>'Inactive','retired'=>'Retired'] as $code=>$label)<option value="{{ $code }}" @selected($device->status===$code)>{{ $label }}</option>@endforeach
                            </select>
                            <input name="reason" required minlength="5" maxlength="1000" placeholder="Reason for status change">
                            <button class="btn btn-small btn-outline">Save device status</button>
                        </form>
                    @endif
                </div>
            @empty <p class="empty-box muted">No registered devices. Actual online/offline connectivity requires a future device API.</p>@endforelse
        </div>
        @if($canManage)
            <div class="divider-space"></div>
            <h2>Register device</h2>
            <form method="POST" action="{{ route('platform.stores.devices.store',$store) }}" class="form-stack">@csrf
                <label for="device_name">Device name</label>
                <input id="device_name" name="name" maxlength="120" required placeholder="Front Counter Terminal">
                <label for="device_type">Device type</label>
                <select id="device_type" name="type" required>
                    <option value="pos_terminal">POS terminal</option>
                    <option value="receipt_printer">Receipt printer</option>
                    <option value="barcode_scanner">Barcode scanner</option>
                </select>
                <label for="device_manufacturer">Manufacturer</label>
                <input id="device_manufacturer" name="manufacturer" maxlength="100" placeholder="Manufacturer (optional)">
                <label for="device_model">Model</label>
                <input id="device_model" name="model" maxlength="120" placeholder="Model (optional)">
                <label for="device_serial">Serial number</label>
                <input id="device_serial" name="serial_number" maxlength="120" placeholder="Unique within this business">
                <button class="btn btn-primary">Register device →</button>
            </form>
        @endif
    </section>
</div>
<section class="panel" style="margin-top:18px">
    <div class="panel-heading"><div><h2>Store activity history</h2><p class="muted">Store changes and device actions, with actor and timestamp</p></div></div>
    <div class="stack-list">
        @forelse($activity as $event)
            <div class="stack-row"><span class="circle-initial">◷</span><span class="stack-grow"><strong>{{ $event->action }}</strong><small>{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('d M Y H:i') }}</small></span></div>
        @empty <p class="muted empty-box">No recorded store-specific activities yet.</p>@endforelse
    </div>
</section>
@endsection

<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Edit business profile</h2><p class="muted">Update the business's public details and accounting defaults.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.profile', $tenant) }}" class="form-stack">
            @csrf @method('PUT')
            <label for="tenant_name">Business name</label>
            <input id="tenant_name" name="name" maxlength="150" required value="{{ old('name', $tenant->name) }}">
            <label for="tenant_contact_email">Contact email</label>
            <input id="tenant_contact_email" name="contact_email" type="email" maxlength="191" value="{{ old('contact_email', $tenant->contact_email) }}">
            <label for="tenant_phone">Phone</label>
            <input id="tenant_phone" name="phone" maxlength="30" value="{{ old('phone', $tenant->phone) }}">
            <label for="tenant_address">Address</label>
            <textarea id="tenant_address" name="address" rows="3" maxlength="1000">{{ old('address', $tenant->address) }}</textarea>
            <label for="tenant_currency">Currency</label>
            <select id="tenant_currency" name="currency">
                @foreach(['LKR','USD'] as $currency)<option value="{{ $currency }}" @selected(old('currency',$tenant->currency) === $currency)>{{ $currency }}</option>@endforeach
            </select>
            <label for="tenant_timezone">Timezone</label>
            <select id="tenant_timezone" name="timezone">
                @foreach(timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone',$tenant->timezone) === $timezone)>{{ $timezone }}</option>@endforeach
            </select>
            <button class="btn btn-secondary" type="submit">Save business profile</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Add branch</h2><p class="muted">Branch limit: {{ $tenant->subscription?->plan?->max_branches ?? 'Unlimited' }} active</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.branches.store', $tenant) }}" class="form-stack">@csrf
            <label for="branch_name">Branch name</label>
            <input id="branch_name" name="name" required maxlength="120" placeholder="Downtown Store">
            <label for="branch_code">Unique branch code</label>
            <input id="branch_code" name="code" required maxlength="30" pattern="[a-zA-Z0-9_-]+" placeholder="DOWNTOWN">
            <label for="branch_phone">Phone (optional)</label>
            <input id="branch_phone" name="phone" maxlength="30">
            <label for="branch_address">Address</label>
            <textarea id="branch_address" name="address" rows="3" maxlength="1000"></textarea>
            <button class="btn btn-primary" type="submit">Add branch</button>
        </form>
    </section>
</div>
<section class="panel" style="margin-top:16px">
    <div class="panel-heading"><div><h2>Manage branches</h2><p class="muted">Existing branches can be edited, deactivated or designated as the default. Historical branch records are never deleted here.</p></div></div>
    <div class="branch-editor-grid">
        @foreach($tenant->branches as $branch)
            <article class="branch-editor">
                <div class="panel-heading">
                    <div><h2>{{ $branch->name }}</h2><p class="muted">{{ $branch->code }}</p></div>
                    <span class="status status-{{ $branch->is_default ? 'active' : ($branch->is_active ? 'trial' : 'suspended') }}">{{ $branch->is_default ? 'Default' : ($branch->is_active ? 'Active' : 'Inactive') }}</span>
                </div>
                <form action="{{ route('platform.tenants.branches.update',[$tenant,$branch]) }}" method="POST" class="form-stack">
                    @csrf @method('PATCH')
                    <label for="branch_name_{{ $branch->id }}">Name</label>
                    <input id="branch_name_{{ $branch->id }}" name="name" maxlength="120" required value="{{ $branch->name }}">
                    <label for="branch_phone_{{ $branch->id }}">Phone</label>
                    <input id="branch_phone_{{ $branch->id }}" name="phone" maxlength="30" value="{{ $branch->phone }}">
                    <label for="branch_address_{{ $branch->id }}">Address</label>
                    <textarea id="branch_address_{{ $branch->id }}" name="address" rows="2" maxlength="1000">{{ $branch->address }}</textarea>
                    <label for="branch_active_{{ $branch->id }}">Status</label>
                    <select name="is_active" id="branch_active_{{ $branch->id }}">
                        <option value="1" @selected($branch->is_active)>Active</option>
                        <option value="0" @selected(!$branch->is_active) @disabled($branch->is_default)>Inactive</option>
                    </select>
                    <button class="btn btn-outline" type="submit">Save branch</button>
                </form>
                @if(!$branch->is_default && $branch->is_active)
                    <form method="POST" action="{{ route('platform.tenants.branches.default',[$tenant,$branch]) }}" style="margin-top:12px">
                        @csrf @method('PATCH')
                        <button class="btn btn-secondary" data-confirm="Set {{ $branch->name }} as the default branch?">Make default branch</button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
</section>

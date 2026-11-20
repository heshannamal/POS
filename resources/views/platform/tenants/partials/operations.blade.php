<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Edit business profile</h2><p class="muted">Update the business's public details and accounting defaults.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.profile', $tenant) }}" class="form-stack">
            @csrf @method('PUT')
            <label for="tenant_name">Business name</label>
            <input id="tenant_name" name="name" maxlength="150" required value="{{ old('name', $tenant->name) }}">
            <label for="tenant_contact_email">Contact email</label>
            <input id="tenant_contact_email" name="contact_email" type="email" maxlength="191" value="{{ old('contact_email', $tenant->contact_email) }}">
            <label for="tenant_contact_person">Contact person</label>
            <input id="tenant_contact_person" name="contact_person" maxlength="150" value="{{ old('contact_person',$tenant->contact_person) }}" placeholder="Primary contact">
            <label for="tenant_phone">Phone</label>
            <input id="tenant_phone" name="phone" maxlength="30" value="{{ old('phone', $tenant->phone) }}">
            <label for="tenant_address">Address</label>
            <textarea id="tenant_address" name="address" rows="3" maxlength="1000">{{ old('address', $tenant->address) }}</textarea>
            <div class="tenant-fields-grid">
                <div><label for="tenant_country">Country (ISO 2-letter code)</label><input id="tenant_country" name="country" maxlength="2" pattern="[a-zA-Z]{2}" value="{{ old('country',$tenant->country) }}" placeholder="LK"></div>
                <div><label for="tenant_website">Website (HTTPS)</label><input type="url" id="tenant_website" name="website" maxlength="255" value="{{ old('website',$tenant->website) }}" placeholder="https://example.com"></div>
                <div><label for="tenant_registration_number">Business registration number</label><input id="tenant_registration_number" name="registration_number" maxlength="100" value="{{ old('registration_number',$tenant->registration_number) }}"></div>
            </div>
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
        <div class="panel-heading"><div><h2>Business logo</h2><p class="muted">Visible to permitted business members and admins. JPEG, PNG or WebP, maximum 2 MB.</p></div></div>
        <div class="tenant-logo-upload">
            @if($tenant->logo_path)
                <img src="{{ route('platform.tenants.logo',$tenant) }}" alt="{{ $tenant->name }} logo" class="tenant-logo-preview">
                <form method="POST" action="{{ route('platform.tenants.logo.delete',$tenant) }}">@csrf @method('DELETE')
                    <button class="btn btn-small btn-outline" type="submit" data-confirm="Remove the current business logo?">Remove logo</button>
                </form>
            @else
                <span class="circle-initial tenant-logo-placeholder">{{ mb_strtoupper(mb_substr($tenant->name,0,1)) }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('platform.tenants.logo.upload',$tenant) }}" enctype="multipart/form-data" class="form-stack">
            @csrf
            <label for="tenant_logo">Upload or replace logo</label>
            <input id="tenant_logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>
            <button class="btn btn-secondary" type="submit">Save logo</button>
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

<div class="dashboard-grid lower-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Internal administrator notes</h2><p class="muted">Only platform Operations and Super Admins can view notes. Note contents are excluded from tenant exports and public audit metadata.</p></div></div>
        <form method="POST" action="{{ route('platform.tenants.notes.store',$tenant) }}" class="form-stack">@csrf
            <label for="new_tenant_note">Add a private note</label>
            <textarea id="new_tenant_note" name="body" rows="3" maxlength="5000" minlength="3" required placeholder="Important details for administrators only">{{ old('body') }}</textarea>
            <button class="btn btn-secondary" type="submit">Save internal note</button>
        </form>
        <div class="tenant-note-feed">
            @forelse($notes as $note)
                <article class="tenant-note">
                    <div class="message-meta"><strong>{{ $note->author?->name ?? 'Former administrator' }}</strong><span>{{ $note->created_at->format('d M Y H:i') }}</span></div>
                    <p>{{ $note->body }}</p>
                    <form method="POST" action="{{ route('platform.tenants.notes.destroy',[$tenant,$note]) }}" class="tenant-note-remove">
                        @csrf @method('DELETE')
                        <input name="reason" maxlength="1000" minlength="5" required aria-label="Reason for deleting note" placeholder="Reason for removal">
                        <button type="submit" class="btn btn-small btn-outline" data-confirm="Delete this internal note with an audit record?">Delete</button>
                    </form>
                </article>
            @empty <p class="muted empty-box">No internal notes recorded yet.</p> @endforelse
        </div>
        @if($notes instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="pagination-line">{{ $notes->links() }}</div>@endif
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Onboarding progress</h2><p class="muted">Track the administrative verification stages for this tenant.</p></div></div>
        <div class="tenant-progress-large"><div class="tenant-progress"><span style="width:{{ $tenant->onboarding_progress }}%"></span></div><strong>{{ $tenant->onboarding_progress }}% — {{ ucfirst($tenant->onboarding_status ?? 'incomplete') }}</strong></div>
        <form method="POST" action="{{ route('platform.tenants.onboarding.update',$tenant) }}" class="form-stack">
            @csrf @method('PATCH')
            <label for="onboarding_status">Onboarding stage</label>
            <select name="onboarding_status" id="onboarding_status" required>
                @foreach(['incomplete'=>'Incomplete (0%)','pending'=>'Pending (25%)','verified'=>'Verified (65%)','completed'=>'Completed (100%)'] as $code=>$label)
                    <option value="{{ $code }}" @selected($tenant->onboarding_status===$code)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="onboarding_reason">Reason for stage change</label>
            <input id="onboarding_reason" name="reason" minlength="5" maxlength="1000" required placeholder="Verification update / missing documents">
            <button type="submit" class="btn btn-outline">Update onboarding progress</button>
        </form>
    </section>
</div>

@extends('layouts.app')
@section('title','Notification Recipients')
@section('header','Notification recipients')
@section('content')
<div class="page-head">
    <div><span class="overline">AUTOMATED EVENT RULES</span><h1>Recipients & channels</h1><p class="muted">Choose which event can send in-app alerts, email, and who should receive them.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.communications.templates.index') }}">← Email templates</a>
</div>
<div class="communication-rule-grid">
@foreach($rules as $rule)
    @php $definition=config('communications.events.'.$rule->event_key,[]); @endphp
    <section class="panel">
        <div class="panel-heading"><div><h2>{{ $definition['name'] ?? $rule->event_key }}</h2><p class="muted">{{ $rule->event_key }}</p></div></div>
        <form method="POST" action="{{ route('platform.communications.rules.update',$rule) }}" class="form-stack">
            @csrf @method('PUT')
            @foreach(['is_enabled'=>'Enable this event','send_in_app'=>'Create in-app alerts','send_email'=>'Allow email delivery','to_owner'=>'Business owner','to_actor'=>'Actor / triggering user','to_platform'=>'Platform administrators (relevant role)'] as $key=>$label)
                <label class="check-line"><input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($rule->{$key})> {{ $label }}</label>
            @endforeach
            <label>Additional email recipients (optional)</label>
            <textarea name="extra_emails" rows="2" maxlength="2000" placeholder="billing@example.com, owner@example.com">{{ implode(', ', $rule->extra_emails ?? []) }}</textarea>
            <p class="muted">These addresses receive email only. Maximum 10. Test mode always redirects delivery to your configured test addresses.</p>
            <button class="btn btn-secondary">Save recipient rule</button>
        </form>
    </section>
@endforeach
</div>
<div class="hint-box" style="margin-top:18px">Changing a recipient rule affects future events only. Existing delivery snapshots cannot be silently redirected to a new address. External sending also requires <a href="{{ route('platform.communications.settings.index') }}">Email Settings</a> to be enabled.</div>
@endsection

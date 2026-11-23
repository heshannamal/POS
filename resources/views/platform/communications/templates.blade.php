@extends('layouts.app')
@section('title','Email Templates')
@section('header','Email templates')
@section('content')
<div class="page-head">
    <div><span class="overline">COMMUNICATION CENTER</span><h1>Email template library</h1><p class="muted">Edit platform messages, placeholders and delivery behavior without changing application source code.</p></div>
    <div class="page-actions">
        <a class="btn btn-primary" href="{{ route('platform.communications.templates.create') }}">＋ Custom template</a>
        <a class="btn btn-outline" href="{{ route('platform.communications.rules.index') }}">Recipient rules</a>
        <a class="btn btn-secondary" href="{{ route('platform.communications.deliveries.index') }}">Delivery history</a>
    </div>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Automatic event templates</h2><p class="muted">Templates are created during migration. Placeholder names are preserved during preview.</p></div></div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Event / template</th><th>Variables</th><th>Template</th><th>In-app</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($templates as $template)
                @php $rule=$rules->get($template->event_key); @endphp
                <tr>
                    <td><strong>{{ $template->name }}</strong><small class="table-subtext">{{ $template->event_key }}</small>@if(str_starts_with($template->event_key,'custom.'))<small class="table-subtext">Manual draft · no event trigger</small>@endif</td>
                    <td><small class="table-subtext">{{ implode(', ', $template->allowed_variables ?? []) }}</small></td>
                    <td><span class="status status-{{ $template->is_active?'active':'suspended' }}">{{ $template->is_active?'Active':'Inactive' }}</span></td>
                    <td>{{ $rule?->send_in_app ? 'Enabled':'Off' }}</td>
                    <td>{{ $rule?->send_email ? 'Permitted':'Off' }}</td>
                    <td><a class="btn btn-small btn-outline" href="{{ route('platform.communications.templates.edit',$template) }}">Edit template →</a></td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
</section>
<div class="hint-box" style="margin-top:16px">You can disable a template without deleting historical messages. To edit recipients or whether an event can email customers, use <a href="{{ route('platform.communications.rules.index') }}">Recipient Rules</a>. Global email sending remains disabled until enabled under <a href="{{ route('platform.communications.settings.index') }}">Email Settings</a>.</div>
@endsection

@extends('layouts.app')
@section('title','Create Custom Email Template')
@section('header','Create email template')
@section('content')
<div class="page-head">
    <div><span class="overline">EMAIL TEMPLATE STUDIO</span><h1>New custom template</h1><p class="muted">Create a reusable message draft. This does not register an automatic event or send messages to anyone.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.communications.templates.index') }}">← Templates</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Message details</h2><p class="muted">Custom drafts are saved separately from the preloaded automated event templates.</p></div></div>
        <form method="POST" action="{{ route('platform.communications.templates.store') }}" class="form-stack">
            @csrf
            <div class="tenant-fields-grid">
                <div><label for="new_template_name">Template name</label><input id="new_template_name" name="name" maxlength="120" required value="{{ old('name') }}" placeholder="Holiday greeting"></div>
                <div><label for="template_slug">Unique identifier</label><input id="template_slug" name="slug" maxlength="55" required pattern="[a-z][a-z0-9_-]+" value="{{ old('slug') }}" placeholder="holiday_greeting"></div>
            </div>
            <label for="template_subject">Email subject</label>
            <input id="template_subject" name="subject" maxlength="255" required value="{{ old('subject','News from POS Cloud') }}">
            <label for="template_body">Email body (Markdown)</label>
            <div class="communication-toolbar" role="toolbar" aria-label="Template formatting">
                <button type="button" class="btn btn-small btn-outline" data-md-format="bold">B</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="italic"><em>I</em></button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="heading">Heading</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="list">List</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="link">Link</button>
            </div>
            <textarea id="template_body" name="body_markdown" data-markdown-editor rows="16" maxlength="30000" required>{{ old('body_markdown',"Hello,\n\nWrite your message here.\n\n— POS Cloud") }}</textarea>
            <button class="btn btn-primary">Create template →</button>
        </form>
        <form method="POST" action="{{ route('platform.communications.templates.preview') }}" id="template-preview-form" target="_blank" class="form-stack" style="margin-top:16px">
            @csrf
            <input type="hidden" name="event_key" value="custom.preview">
            <input type="hidden" name="subject" id="preview-subject">
            <textarea name="body_markdown" id="preview-body" hidden></textarea>
            <button class="btn btn-secondary" type="submit" data-template-preview>Open visual preview ↗</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Allowed custom variables</h2><p class="muted">Click a placeholder to add it at your cursor.</p></div></div>
        <div class="communication-variables">
            @foreach(config('communications.custom_variables',[]) as $name)
                @php $placeholder = '{'.'{'.$name.'}'.'}'; @endphp
                <button type="button" class="communication-variable" data-insert-variable="{{ $placeholder }}">{{ $placeholder }}</button>
            @endforeach
        </div>
        <div class="hint-box" style="margin-top:16px">These are placeholders for future manual workflows. Creating a custom draft does not make its variables available automatically, create a recipient list, or enable SMTP. Built-in event templates are the only messages that automation sends in this release.</div>
    </section>
</div>
@endsection

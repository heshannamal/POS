@extends('layouts.app')
@section('title','Edit email template')
@section('header','Email template editor')
@section('content')
<div class="page-head">
    <div><span class="overline">EMAIL DESIGN STUDIO</span><h1>{{ $template->name }}</h1><p class="muted">{{ $template->event_key }} · Edit formatting, text and allowed placeholders; test the preview before activating the template.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.communications.templates.index') }}">← Templates</a>
</div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Edit email contents</h2><p class="muted">Markdown formatting with visual preview. HTML is safely generated on the server: scripts and arbitrary HTML are removed.</p></div></div>
        <form method="POST" action="{{ route('platform.communications.templates.update',$template) }}" class="form-stack" id="communication-editor">
            @csrf @method('PUT')
            <label for="template_name">Template name</label>
            <input id="template_name" name="name" maxlength="120" required value="{{ old('name',$template->name) }}">
            <label for="template_subject">Subject</label>
            <input id="template_subject" name="subject" maxlength="255" required value="{{ old('subject',$template->subject) }}">
            <label for="template_body">Message body</label>
            <div class="communication-toolbar" role="toolbar" aria-label="Email formatting">
                <button type="button" class="btn btn-small btn-outline" data-md-format="bold">B</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="italic"><em>I</em></button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="heading">Heading</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="list">List</button>
                <button type="button" class="btn btn-small btn-outline" data-md-format="link">Link</button>
            </div>
            <textarea id="template_body" name="body_markdown" rows="16" maxlength="30000" required data-markdown-editor>{{ old('body_markdown',$template->body_markdown) }}</textarea>
            <div class="communication-editor-actions">
                <label class="check-line"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$template->is_active))> Template active</label>
                <button type="submit" class="btn btn-primary">Save email template</button>
            </div>
        </form>
        <form method="POST" action="{{ route('platform.communications.templates.preview') }}" target="_blank" class="form-stack" id="template-preview-form" style="margin-top:16px">
            @csrf
            <input type="hidden" name="event_key" value="{{ $template->event_key }}">
            <input type="hidden" name="subject" id="preview-subject">
            <textarea name="body_markdown" id="preview-body" hidden></textarea>
            <button type="submit" class="btn btn-secondary" data-template-preview>Open visual preview ↗</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Available variables</h2><p class="muted">Click to insert into the editor at the cursor. Variables stay unchanged in preview.</p></div></div>
        <div class="communication-variables">
        @foreach($template->allowed_variables ?? [] as $variable)
            @php $placeholder = '{'.'{'.$variable.'}'.'}'; @endphp
            <button class="communication-variable" type="button" data-insert-variable="{{ $placeholder }}">{{ $placeholder }}</button>
        @endforeach
        </div>
        <div class="hint-box" style="margin-top:16px">
            <strong>Formatting examples</strong>
            <p><code>**Bold text**</code> · <code>*Italic text*</code></p>
            <p><code>[Open dashboard](@verbatim{{business_url}}@endverbatim)</code></p>
            <p>Use the preview button to see the server-rendered HTML. Invalid variables are rejected when saving.</p>
        </div>
        <div class="hint-box" style="margin-top:16px">This template controls Phase 5 event messages. Existing password-reset and invitation emails in <code>config/pos_emails.php</code> are kept for compatibility; their credentials also use your private <code>.env</code>.</div>
    </section>
</div>
@endsection

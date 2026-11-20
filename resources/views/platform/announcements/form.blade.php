@extends('layouts.app')
@section('title',$announcement ? 'Edit announcement' : 'Create announcement')
@section('header',$announcement ? 'Edit announcement' : 'New announcement')
@section('content')
<div class="page-head">
    <div><span class="overline">PLATFORM MESSAGING</span><h1>{{ $announcement ? 'Update announcement' : 'Create announcement' }}</h1><p class="muted">Notices appear on business dashboards only when published and within the selected dates.</p></div>
    <a class="btn btn-outline" href="{{ route('platform.announcements.index') }}">← Announcements</a>
</div>
<section class="panel form-panel">
    <form method="POST" class="form-stack" action="{{ $announcement ? route('platform.announcements.update', $announcement) : route('platform.announcements.store') }}">
        @csrf @if($announcement) @method('PUT') @endif
        <label for="title">Headline *</label>
        <input name="title" id="title" maxlength="160" required value="{{ old('title',$announcement?->title) }}" placeholder="Scheduled maintenance notice">
        <label for="message">Message *</label>
        <textarea id="message" name="message" rows="7" maxlength="5000" required placeholder="Message shown to business owners...">{{ old('message',$announcement?->message) }}</textarea>
        <label for="tone">Notification tone</label>
        <select name="tone" id="tone">
            @foreach(['info'=>'Information','success'=>'Success','warning'=>'Warning'] as $key=>$label)
                <option value="{{ $key }}" @selected(old('tone',$announcement?->tone ?? 'info') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="starts_at">Visible from (optional)</label>
        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at',$announcement?->starts_at?->format('Y-m-d\TH:i')) }}">
        <label for="ends_at">Visible until (optional)</label>
        <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at',$announcement?->ends_at?->format('Y-m-d\TH:i')) }}">
        <label for="is_published">Publication</label>
        <select id="is_published" name="is_published"><option value="0" @selected(!old('is_published',$announcement?->is_published ?? false))>Draft</option><option value="1" @selected(old('is_published',$announcement?->is_published ?? false))>Publish</option></select>
        <button type="submit" class="btn btn-primary">Save announcement →</button>
    </form>
</section>
@endsection

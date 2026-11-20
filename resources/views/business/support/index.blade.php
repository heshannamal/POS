@extends('layouts.app')
@section('title','Support')
@section('header','Support requests')
@section('content')
<div class="page-head"><div><span class="overline">BUSINESS SUPPORT</span><h1>How can we help?</h1><p class="muted">Open a platform support request for your business, or follow up on an existing case.</p></div></div>
<div class="dashboard-grid">
    <section class="panel panel-wide">
        <div class="panel-heading"><div><h2>Your support requests</h2><p class="muted">Visible only to authorized members of {{ $tenant->name }}.</p></div></div>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Reference</th><th>Subject</th><th>Category</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr><td>#{{ str_pad($ticket->id,6,'0',STR_PAD_LEFT) }}</td><td><strong>{{ $ticket->subject }}</strong></td><td>{{ ucfirst($ticket->category) }}</td><td><span class="status status-trial">{{ ucwords(str_replace('_',' ',$ticket->status)) }}</span></td><td>{{ $ticket->updated_at->format('d M Y') }}</td><td><a class="text-link" href="{{ route('business.support.show',[$tenant,$ticket]) }}">Open →</a></td></tr>
                @empty <tr><td colspan="6" class="empty-row">No support requests yet.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="pagination-line">{{ $tickets->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Create a support request</h2><p class="muted">Describe your question and our team can reply here.</p></div></div>
        <form method="POST" action="{{ route('business.support.store',$tenant) }}" class="form-stack">@csrf
            <label for="subject">Subject</label>
            <input id="subject" name="subject" maxlength="160" required value="{{ old('subject') }}" placeholder="Issue with employee access">
            <label for="category">Category</label>
            <select id="category" name="category">@foreach(['general'=>'General','technical'=>'Technical','billing'=>'Billing','account'=>'Business account'] as $key=>$label)<option value="{{ $key }}" @selected(old('category')===$key)>{{ $label }}</option>@endforeach</select>
            <label for="message">Describe the issue</label>
            <textarea id="message" name="message" rows="5" maxlength="5000" required placeholder="Include details our support team needs...">{{ old('message') }}</textarea>
            <button class="btn btn-primary">Submit request →</button>
        </form>
    </section>
</div>
@endsection

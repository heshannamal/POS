@extends('layouts.app')
@section('title','Feature modules')
@section('header','Module registry')
@section('content')
<div class="page-head">
    <div><span class="overline">FEATURE CATALOG</span><h1>Modules & availability</h1><p class="muted">Manage descriptions and prices. A feature can only be released through tested application code.</p></div>
    <a href="{{ route('platform.modules.create') }}" class="btn btn-primary">＋ New planned module</a>
</div>
<section class="panel">
    <form method="GET" action="{{ route('platform.modules.index') }}" class="filter-row">
        <input type="search" name="search" placeholder="Search module code, name or category..." value="{{ request('search') }}">
        <button class="btn btn-secondary">Search modules</button>
        <a href="{{ route('platform.modules.index') }}" class="btn btn-outline">Clear</a>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Module</th><th>Category</th><th>Included in plans</th><th>Monthly add-on (catalog)</th><th>Release</th><th></th></tr></thead>
            <tbody>
            @forelse($modules as $module)
                <tr>
                    <td><div class="table-identity"><span class="circle-initial">◇</span><div><strong>{{ $module->name }}</strong><small>{{ $module->code }}</small></div></div></td>
                    <td>{{ $module->category }}</td>
                    <td>{{ $module->plans_count }}</td>
                    <td>LKR {{ number_format($module->monthly_price_minor / 100, 2) }}</td>
                    <td><span class="status status-{{ $module->is_available ? 'active' : 'trial' }}">{{ $module->is_available ? 'Released' : 'Planned' }}</span></td>
                    <td><a class="btn btn-outline btn-small" href="{{ route('platform.modules.edit', $module) }}">Configure →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-row">No modules match the search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-line">{{ $modules->links() }}</div>
</section>
<div class="hint-box" style="margin-top:16px">Feature access also requires a valid tenant subscription, its plan or an explicit override, and the module's released status. Module catalog prices do not create charges or invoices.</div>
@endsection

@extends('layouts.app')
@section('title', 'System health')
@section('content')
<x-page-header title="System health" subtitle="Services, queues and errors. Counts and error codes only; no organization content is shown." />

@if($runtime['debug'] && $runtime['env'] === 'production')
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div><b>APP_DEBUG is on in production.</b> Turn it off in <code>.env</code>.</div></div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat label="AI service" :value="($checks['ai']['status'] ?? '') === 'ok' ? 'Online' : 'Unreachable'" icon="bi-cpu" :tone="($checks['ai']['status'] ?? '') === 'ok' ? 'success' : 'danger'" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Database" :value="$checks['database'] ? 'Online' : 'Down'" icon="bi-database" :tone="$checks['database'] ? 'success' : 'danger'" /></div>
    <div class="col-6 col-xl-3"><x-stat label="File storage" :value="$checks['storage'] ? 'Writable' : 'Not writable'" icon="bi-hdd" :tone="$checks['storage'] ? 'success' : 'danger'" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Failed queue jobs" :value="$queue['failed']" icon="bi-collection" :tone="$queue['failed'] ? 'warning' : 'success'" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="nx-card h-100"><div class="nx-card-head"><h2>Queue</h2></div>
        <ul class="nx-list">
            <li><span>Driver</span><code>{{ $queue['driver'] }}</code></li>
            <li><span>Waiting jobs</span><b>{{ $queue['pending'] }}</b></li>
            <li><span>Failed jobs</span><b>{{ $queue['failed'] }}</b></li>
            @foreach(['pending', 'processing', 'completed', 'failed'] as $s)<li><span>Document processing: {{ $s }}</span><b>{{ $processing[$s] ?? 0 }}</b></li>@endforeach
        </ul></div></div>
    <div class="col-lg-4"><div class="nx-card h-100"><div class="nx-card-head"><h2>AI service &amp; index</h2></div>
        <ul class="nx-list">
            <li><span>Embedder</span><span class="text-muted">{{ $checks['ai']['embedder'] ?? '-' }}</span></li>
            <li><span>Answer engine</span><span class="text-muted">{{ $checks['ai']['llm'] ?? '-' }}</span></li>
            <li><span>Documents indexed</span><b>{{ $checks['index']['documents'] ?? ($checks['ai']['documents_indexed'] ?? '-') }}</b></li>
            <li><span>Index consistency</span>@isset($checks['index']['ok'])<x-status :status="$checks['index']['ok'] ? 'ok' : 'failed'" />@else<span class="text-muted">unknown</span>@endisset</li>
            @if(!empty($checks['ai']['llm_warning']))<li class="small text-warning">{{ $checks['ai']['llm_warning'] }}</li>@endif
        </ul></div></div>
    <div class="col-lg-4"><div class="nx-card h-100"><div class="nx-card-head"><h2>Runtime</h2></div>
        <ul class="nx-list">
            <li><span>PHP</span><span class="text-muted">{{ $runtime['php'] }}</span></li>
            <li><span>Laravel</span><span class="text-muted">{{ $runtime['laravel'] }}</span></li>
            <li><span>Environment</span><span class="text-muted">{{ $runtime['env'] }}</span></li>
            <li><span>Debug mode</span><span class="text-muted">{{ $runtime['debug'] ? 'on' : 'off' }}</span></li>
        </ul></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5"><div class="nx-card h-100"><div class="nx-card-head"><h2>Recent processing failures</h2></div>
        <ul class="nx-list small">
            @forelse($recentFailures as $failure)
                <li><span><b>{{ $failure->organization }}</b><br><span class="text-muted">{{ $failure->error_message }}</span></span><code>{{ $failure->error_code }}</code></li>
            @empty<li class="text-muted">No failures recorded.</li>@endforelse
        </ul></div></div>
    <div class="col-lg-7"><div class="nx-card h-100"><div class="nx-card-head"><h2>Application errors</h2><span class="small text-muted">last {{ count($errorLog) }}</span></div>
        <ul class="nx-list small">
            @forelse($errorLog as $line)
                <li><span><x-status status="failed" /> <span class="text-muted">{{ $line['time'] }}</span><br>{{ $line['message'] }}</span></li>
            @empty<li class="text-muted">No errors in the log.</li>@endforelse
        </ul>
        <div class="nx-card-body border-top small text-muted"><i class="bi bi-lock me-1"></i> Stack traces, SQL details and e-mail addresses are hidden here. Open <code>storage/logs/laravel.log</code> on the server for full details.</div></div></div>
</div>
@endsection

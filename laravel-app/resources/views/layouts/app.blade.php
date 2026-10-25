<!DOCTYPE html>
<html lang="en">
<head>@include('layouts.head')</head>
<body>
@php
    $me = auth()->user();
    $brand = config('nexora.brand');
    // [permission needed, route, label, icon, route names that mark the item as active]
    $menu = [
        ['dashboard.view', 'dashboard', 'Dashboard', 'bi-grid-1x2', ['dashboard']],
        ['documents.view', 'documents.index', 'Documents', 'bi-folder2-open', ['documents.index', 'documents.show', 'documents.edit', 'documents.related']],
        ['documents.upload', 'documents.create', 'Upload', 'bi-cloud-arrow-up', ['documents.create']],
        ['search.use', 'search.index', 'Search', 'bi-search', ['search.*']],
        ['chat.use', 'chat.index', 'Ask AI', 'bi-stars', ['chat.*']],
        ['reviews.view', 'reviews.index', 'Reviews', 'bi-patch-check', ['reviews.*']],
        ['users.manage', 'users.index', $me->isSuper() ? 'Organization admins' : 'Users', 'bi-people', ['users.*']],
        ['departments.manage', 'departments.index', 'Departments', 'bi-diagram-3', ['departments.*']],
        ['organizations.manage', 'organizations.index', 'Organizations', 'bi-buildings', ['organizations.*']],
        ['security.manage', 'admin.accounts', 'Account recovery', 'bi-key', ['admin.accounts*']],
        ['security.manage', 'admin.incidents', 'Security incidents', 'bi-shield-exclamation', ['admin.incidents*']],
        ['system.view', 'admin.system', 'System health', 'bi-activity', ['admin.system']],
        ['audit.view', 'audit.index', 'Audit log', 'bi-journal-text', ['audit.*']],
    ];
@endphp

<aside class="nx-sidebar" id="sidebar">
    <a class="nx-brand" href="{{ route('dashboard') }}">
        @include('partials.logo')
        <div><strong>{{ $brand['name'] }}</strong><small>{{ $brand['tagline'] }}</small></div>
    </a>
    <nav class="nx-nav">
        @foreach($menu as [$permission, $route, $label, $icon, $activeOn])
            @if($me->hasPermission($permission))
                <a class="nx-link {{ request()->routeIs(...$activeOn) ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></a>
            @endif
        @endforeach
    </nav>
    <div class="nx-user">
        <a class="nx-avatar" href="{{ route('profile.edit') }}" title="My profile">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</a>
        <div class="meta"><b>{{ $me->name }}</b><span>{{ $me->roleLabel() }}</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" title="Sign out"><i class="bi bi-box-arrow-right"></i></button></form>
    </div>
</aside>

<div class="nx-main">
    <header class="nx-topbar">
        <button class="btn btn-ghost d-lg-none" id="menuButton" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
        <span class="crumb">@yield('title')</span>
        <span class="nx-org-chip"><i class="bi {{ $me->isSuper() ? 'bi-shield-lock' : 'bi-building' }}"></i>
            {{ $me->isSuper() ? 'Platform administration' : ($me->organization?->name ?? 'No organization') }}</span>
    </header>
    <main class="nx-content">
        @include('partials.flash')
        @yield('content')
    </main>
    <footer class="nx-footer">© {{ date('Y') }} {{ $brand['name'] }}</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile menu
    document.getElementById('menuButton')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
    document.addEventListener('click', e => { if (!e.target.closest('#sidebar, #menuButton')) document.getElementById('sidebar')?.classList.remove('open'); });
    // Confirm before destructive actions:  <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    // Loading state on submit:  <form data-loading="Saving...">
    document.querySelectorAll('form[data-loading]').forEach(f => f.addEventListener('submit', e => {
        if (e.defaultPrevented) return;
        const b = f.querySelector('button[type=submit]');
        if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (f.dataset.loading || 'Working...'); }
    }));
    // Example question chips:  <button data-fill="#target" data-text="...">
    document.querySelectorAll('[data-fill]').forEach(b => b.addEventListener('click', () => { const t = document.querySelector(b.dataset.fill); t.value = b.dataset.text; t.focus(); }));
</script>
@stack('scripts')
</body>
</html>

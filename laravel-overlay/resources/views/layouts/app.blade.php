<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GovDoc BD') - GovDoc BD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f7fa; }
        .navbar-brand small { font-size:.65rem; }
        .status-badge { text-transform:capitalize; }
        .citation { border-left:3px solid #0d6efd; background:#f1f6ff; }
        .msg-user { background:#e7f1ff; } .msg-ai { background:#fff; }
        pre.snippet { white-space:pre-wrap; font-family:inherit; margin:0; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('dashboard') }}">GovDoc BD <small class="badge bg-warning text-dark">Academic prototype</small></a>
        @auth
        @php $me = auth()->user(); @endphp
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                @if($me->hasPermission('dashboard.view'))<li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>@endif
                @if($me->hasPermission('documents.view'))<li class="nav-item"><a class="nav-link" href="{{ route('documents.index') }}">Documents</a></li>@endif
                @if($me->hasPermission('documents.upload'))<li class="nav-item"><a class="nav-link" href="{{ route('documents.create') }}">Upload</a></li>@endif
                @if($me->hasPermission('search.use'))<li class="nav-item"><a class="nav-link" href="{{ route('search.index') }}">Search</a></li>@endif
                @if($me->hasPermission('chat.use'))<li class="nav-item"><a class="nav-link" href="{{ route('chat.index') }}">Ask AI</a></li>@endif
                @if($me->hasPermission('reviews.view'))<li class="nav-item"><a class="nav-link" href="{{ route('reviews.index') }}">Reviews</a></li>@endif
                @if($me->hasPermission('users.manage'))<li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">Users</a></li>@endif
                @if($me->hasPermission('departments.manage'))<li class="nav-item"><a class="nav-link" href="{{ route('departments.index') }}">Departments</a></li>@endif
                @if($me->hasPermission('organizations.manage'))<li class="nav-item"><a class="nav-link" href="{{ route('organizations.index') }}">Organizations</a></li>@endif
                @if($me->hasPermission('audit.view'))<li class="nav-item"><a class="nav-link" href="{{ route('audit.index') }}">Audit log</a></li>@endif
            </ul>
            <div class="dropdown">
                <a class="btn btn-outline-light btn-sm dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ $me->name }} <span class="badge bg-secondary">{{ $me->roleLabel() }}</span></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Log out</button></form></li>
                </ul>
            </div>
        </div>
        @endauth
    </div>
</nav>

<main class="container-fluid px-4">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>

<footer class="container-fluid px-4 my-4 text-muted small">
    GovDoc BD is a student capstone prototype using synthetic data. It is not affiliated with or authorized by the Government of Bangladesh.
    AI output can be wrong and is not verified fact until a reviewer confirms it; always check the source document.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
document.querySelectorAll('form[data-loading]').forEach(f => f.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const b = f.querySelector('button[type=submit]');
    if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (f.dataset.loading || 'Working...'); }
}));
</script>
</body>
</html>

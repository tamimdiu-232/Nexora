<!DOCTYPE html>
<html lang="en">
<head>@include('layouts.head')</head>
<body>
@php $brand = config('nexora.brand'); @endphp
<div class="nx-auth">
    <section class="nx-auth-brand">
        <div>
            @include('partials.logo')
            <h1>{{ $brand['name'] }}</h1>
            <div class="tag">{{ $brand['tagline'] }}</div>
            <ul>
                <li><i class="bi bi-search"></i><span>Find anything across your documents, even scanned ones.</span></li>
                <li><i class="bi bi-chat-square-text"></i><span>Ask questions and get answers with the exact source page.</span></li>
                <li><i class="bi bi-shield-check"></i><span>Role-based access, human review and a full audit trail.</span></li>
            </ul>
        </div>
        <small class="text-white-50">© {{ date('Y') }} {{ $brand['name'] }}</small>
    </section>
    <section class="nx-auth-form"><div class="inner">
        @include('partials.flash')
        @yield('content')
    </div></section>
</div>
<script>
    document.querySelectorAll('form[data-loading]').forEach(f => f.addEventListener('submit', e => {
        const b = f.querySelector('button[type=submit]');
        if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (f.dataset.loading || 'Working...'); }
    }));
</script>
</body>
</html>

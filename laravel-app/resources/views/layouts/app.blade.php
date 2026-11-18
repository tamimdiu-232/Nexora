<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('nexora.brand.name', 'Nexora') }} — @yield('title', 'Document Intelligence')</title>

    <!-- Google Fonts: Inter & Noto Sans Bengali -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Nexora Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/nexora.css') }}">
</head>
<body>
    @php
        $user = Auth::user();
        
        // Single source of truth menu array: [permission, route, label, icon, activePatterns]
        $navItems = [
            // Organization Plane
            ['dashboard.view', 'dashboard', 'Dashboard', 'bi-speedometer2', ['dashboard*']],
            ['documents.view', 'documents.index', 'Documents', 'bi-files', ['documents*']],
            ['search.use', 'search.index', 'Search', 'bi-search', ['search*']],
            ['chat.use', 'chat.index', 'Ask AI', 'bi-stars', ['chat*']],
            ['reviews.view', 'reviews.index', 'Reviews', 'bi-patch-check', ['reviews*']],
            ['departments.manage', 'departments.index', 'Departments', 'bi-building', ['departments*']],
            ['users.manage', 'users.index', 'Users', 'bi-people', ['users*']],

            // Platform Plane (Super Admin)
            ['system.view', 'platform.dashboard', 'Platform Overview', 'bi-grid-1x2', ['platform']],
            ['organizations.manage', 'organizations.index', 'Organizations', 'bi-diagram-3', ['organizations*']],
            ['system.view', 'platform.health', 'System Health', 'bi-activity', ['platform/health*']],
            ['security.manage', 'platform.recovery', 'Account Recovery', 'bi-key', ['platform/recovery*']],
            ['security.manage', 'platform.incidents', 'Security Incidents', 'bi-shield-exclamation', ['platform/incidents*']],

            // Shared / Scoped
            ['audit.view', 'audit.index', 'Audit Log', 'bi-journal-text', ['audit*']],
        ];

        // Initials avatar
        $nameParts = explode(' ', $user?->name ?? 'User');
        $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
    @endphp

    <div class="nx-wrapper">
        <!-- Sidebar -->
        <aside class="nx-sidebar" id="nxSidebar">
            <div class="nx-sidebar-brand">
                <a href="{{ route('dashboard') }}" class="nx-brand-title">
                    @include('partials.logo')
                    <span>{{ config('nexora.brand.name', 'Nexora') }}</span>
                </a>
                <div class="nx-brand-tagline">
                    {{ config('nexora.brand.tagline', 'Next-generation organizational intelligence') }}
                </div>
            </div>

            <nav class="nx-sidebar-nav">
                @if($user?->isSuperAdmin())
                    <div class="nx-nav-section-title">Platform Plane</div>
                @else
                    <div class="nx-nav-section-title">Workspace</div>
                @endif

                @foreach($navItems as $item)
                    @php
                        [$perm, $routeName, $label, $icon, $patterns] = $item;
                        $hasAccess = $user && $user->hasPermission($perm);
                        
                        // Extra check: super_admin only sees platform routes and organizations
                        if ($user?->isSuperAdmin() && in_array($routeName, ['dashboard', 'documents.index', 'search.index', 'chat.index', 'reviews.index', 'departments.index'])) {
                            $hasAccess = false;
                        }
                        if (!$user?->isSuperAdmin() && in_array($routeName, ['platform.dashboard', 'platform.health', 'platform.recovery', 'platform.incidents', 'organizations.index'])) {
                            $hasAccess = false;
                        }

                        $isActive = false;
                        foreach ($patterns as $pattern) {
                            if (request()->is($pattern)) {
                                $isActive = true;
                                break;
                            }
                        }
                    @endphp

                    @if($hasAccess)
                        <a href="{{ route($routeName) }}" class="nx-nav-link {{ $isActive ? 'active' : '' }}">
                            <i class="bi {{ $icon }}"></i>
                            <span>{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="nx-sidebar-footer">
                <div class="nx-user-card">
                    <div class="nx-avatar-initials">{{ $initials }}</div>
                    <div class="flex-grow-1 text-truncate">
                        <div class="small fw-semibold text-white text-truncate">{{ $user?->name }}</div>
                        <div class="text-white-50 small text-truncate" style="font-size: 0.72rem;">
                            {{ config('nexora.roles.' . $user?->role, $user?->role) }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-link text-white-50 p-1 hover-text-white" title="Sign out">
                            <i class="bi bi-box-arrow-right fs-5"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="nx-main">
            <!-- Topbar -->
            <header class="nx-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-outline-secondary d-lg-none" id="nxSidebarToggle" aria-label="Toggle navigation">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="fw-semibold text-dark fs-6 d-none d-sm-block">
                        @yield('page_title', config('nexora.brand.name'))
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    @if($user?->isSuperAdmin())
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-2 px-3 fw-medium">
                            <i class="bi bi-shield-lock-fill me-1"></i> Platform Administration
                        </span>
                    @elseif($user?->organization)
                        <span class="badge bg-light text-secondary border py-2 px-3 fw-normal">
                            <i class="bi bi-building me-1 text-primary"></i>
                            <strong>{{ $user->organization->name }}</strong>
                            @if($user->department)
                                <span class="mx-1 text-muted">/</span> {{ $user->department->name }}
                            @endif
                        </span>
                    @endif

                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                            <span class="nx-avatar-initials" style="width: 24px; height: 24px; font-size: 0.7rem;">{{ $initials }}</span>
                            <span class="small d-none d-md-inline">{{ $user?->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item small" href="{{ route('profile.show') }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item small text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="nx-content">
                @include('partials.flash')
                @yield('content')
            </main>

            <!-- Footer (Clean: © Year Nexora) -->
            <footer class="nx-footer">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                    <div>
                        &copy; {{ date('Y') }} {{ config('nexora.brand.name', 'Nexora') }}. All rights reserved.
                    </div>
                    <div class="text-muted small">
                        {{ config('nexora.brand.tagline', 'Next-generation organizational intelligence') }}
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="{{ asset('js/nexora.js') }}"></script>
    @stack('scripts')
</body>
</html>

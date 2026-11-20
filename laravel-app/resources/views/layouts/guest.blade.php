<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('nexora.brand.name', 'Nexora') }} — @yield('title', 'Sign In')</title>

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
    <div class="nx-guest-container">
        <!-- Brand Presentation Panel -->
        <div class="nx-guest-brand-panel">
            <div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    @include('partials.logo')
                    <span class="fs-3 fw-bold text-white tracking-tight">{{ config('nexora.brand.name', 'Nexora') }}</span>
                </div>
                <p class="text-white-50 fs-6 mb-5">
                    {{ config('nexora.brand.tagline', 'Next-generation organizational intelligence') }}
                </p>

                <!-- Value Pillars -->
                <div class="d-flex flex-column gap-4 my-5">
                    <div class="d-flex align-items-start gap-3">
                        <div class="nx-stat-icon primary bg-white bg-opacity-10 text-white rounded-circle flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem;">
                            <i class="bi bi-archive-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1 fw-semibold">Institutional Document Memory</h6>
                            <p class="text-white-50 small mb-0">High-fidelity OCR and structured indexing across digital and scanned English & Bengali documents.</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="nx-stat-icon success bg-white bg-opacity-10 text-white rounded-circle flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem;">
                            <i class="bi bi-file-earmark-check-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1 fw-semibold">Grounded AI Answers & Citations</h6>
                            <p class="text-white-50 small mb-0">Deterministic fact retrieval with precise document and page-level source citations and conflict detection.</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="nx-stat-icon info bg-white bg-opacity-10 text-white rounded-circle flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-1 fw-semibold">Human-in-the-Loop Verification</h6>
                            <p class="text-white-50 small mb-0">Reviewer workflows and strict department/tenant boundary enforcement across organizational planes.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-white-50 small">
                &copy; {{ date('Y') }} {{ config('nexora.brand.name', 'Nexora') }}. All rights reserved.
            </div>
        </div>

        <!-- Form Panel -->
        <div class="nx-guest-form-panel">
            <div class="nx-guest-card">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="{{ asset('js/nexora.js') }}"></script>
</body>
</html>

@props(['title', 'subtitle' => null])

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-muted small mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($slot) && !empty(trim($slot)))
        <div class="d-flex align-items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>

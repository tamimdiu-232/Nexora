@props(['icon' => 'bi-inbox', 'title' => 'No records found', 'subtitle' => null])

<div class="text-center py-5">
    <div class="display-4 text-muted opacity-50 mb-3">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h5 class="fw-semibold text-secondary mb-1">{{ $title }}</h5>
    @if($subtitle)
        <p class="text-muted small mb-3">{{ $subtitle }}</p>
    @endif
    @if(isset($slot) && !empty(trim($slot)))
        <div class="mt-3">
            {{ $slot }}
        </div>
    @endif
</div>

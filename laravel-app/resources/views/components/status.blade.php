@props(['status'])

@php
    $cleanStatus = strtolower(str_replace(' ', '_', $status ?? 'pending'));
    $label = ucwords(str_replace('_', ' ', $status ?? 'pending'));
    
    // Status icons
    $iconMap = [
        'completed' => 'bi-check-circle-fill',
        'verified' => 'bi-patch-check-fill',
        'active' => 'bi-check-circle-fill',
        'resolved' => 'bi-shield-check',
        'processing' => 'bi-arrow-repeat',
        'answered' => 'bi-chat-left-text-fill',
        'open' => 'bi-exclamation-circle-fill',
        'pending' => 'bi-clock-fill',
        'investigating' => 'bi-search',
        'low' => 'bi-info-circle',
        'medium' => 'bi-exclamation-circle',
        'failed' => 'bi-x-circle-fill',
        'rejected' => 'bi-x-octagon-fill',
        'inactive' => 'bi-dash-circle-fill',
        'error' => 'bi-exclamation-triangle-fill',
        'high' => 'bi-exclamation-diamond-fill',
        'critical' => 'bi-radioactive',
        'insufficient_evidence' => 'bi-question-circle-fill',
        'needs_correction' => 'bi-pencil-square',
        'conflicting_evidence' => 'bi-exclamation-triangle-fill',
    ];
    $icon = $iconMap[$cleanStatus] ?? 'bi-circle-fill';
@endphp

<span class="nx-badge nx-badge-{{ $cleanStatus }}">
    <i class="bi {{ $icon }}"></i>
    <span>{{ $label }}</span>
</span>

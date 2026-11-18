@props(['label', 'value', 'icon', 'tone' => 'primary'])

<div class="nx-stat-card">
    <div class="nx-stat-icon {{ $tone }}">
        <i class="bi {{ $icon }}"></i>
    </div>
    <div>
        <div class="nx-stat-value">{{ $value }}</div>
        <div class="nx-stat-label">{{ $label }}</div>
    </div>
</div>

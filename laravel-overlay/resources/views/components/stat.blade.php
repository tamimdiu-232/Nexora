@props(['label', 'value', 'icon', 'tone' => 'primary'])
<div class="nx-card nx-stat">
    <div class="icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></div>
    <div><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
</div>

@props(['icon' => 'bi-inbox', 'title'])
<div class="nx-empty">
    <i class="bi {{ $icon }}"></i>
    <h3>{{ $title }}</h3>
    <div>{{ $slot }}</div>
</div>

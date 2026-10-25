@props(['title', 'subtitle' => null])
<div class="nx-page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    <div class="nx-actions">{{ $slot }}</div>
</div>

@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'headingId' => null,
])

<header {{ $attributes->class(['blade-workspace-header']) }}>
    <div>
        @if ($eyebrow)
            <p class="blade-dashboard-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 @if ($headingId) id="{{ $headingId }}" @endif>{{ $title }}</h1>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <nav class="blade-workspace-actions" aria-label="Page actions">
            {{ $actions }}
        </nav>
    @endisset
</header>

@props([
    'title' => null,
    'eyebrow' => null,
    'meta' => null,
])

<article {{ $attributes->class(['blade-dashboard-card']) }}>
    @if ($title || $eyebrow || $meta || isset($actions))
        <header class="blade-dashboard-section-title">
            <div>
                @if ($eyebrow)<span class="blade-dashboard-label">{{ $eyebrow }}</span>@endif
                @if ($title)<h2>{{ $title }}</h2>@endif
            </div>
            @isset($actions)
                <div class="blade-workspace-actions">{{ $actions }}</div>
            @elseif ($meta)
                <small>{{ $meta }}</small>
            @endisset
        </header>
    @endif
    {{ $slot }}
</article>

@props([
    'links' => [],
    'active' => 'overview',
])

@if ($links !== [])
    <nav {{ $attributes->class('people-profile-nav') }} aria-label="Employee 360 sections">
        @foreach ($links as $link)
            <a
                href="{{ $link['url'] }}"
                @class(['is-active' => $active === $link['key']])
                @if ($active === $link['key']) aria-current="page" @endif
            >{{ $link['label'] }}</a>
        @endforeach
    </nav>
@endif

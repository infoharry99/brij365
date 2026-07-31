@props(['tone' => 'default'])

<span {{ $attributes->class(['blade-status-pill', 'b360-tone-'.$tone]) }}>{{ $slot }}</span>

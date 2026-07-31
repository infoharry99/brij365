@props([
    'href' => null,
    'variant' => 'secondary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'primary' => 'blade-primary-action',
        'danger' => 'blade-danger-action',
        default => 'blade-secondary-action',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif

@props([
    'user' => null,
    'label' => null,
    'class' => 'b360-avatar',
])

@php
    $name = $user?->name ?? $label ?? 'User';
    $initial = str($name)->substr(0, 1)->upper();
@endphp

@if ($user?->profile_photo_path)
    <img
        {{ $attributes->class([$class, 'b360-user-avatar-image']) }}
        src="{{ route('builder360.profile-photo.show', $user) }}"
        alt="{{ $name }}"
        loading="lazy"
        decoding="async"
    >
@else
    <span {{ $attributes->class([$class]) }} aria-label="{{ $name }}">{{ $initial }}</span>
@endif

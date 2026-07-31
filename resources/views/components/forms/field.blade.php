@props([
    'name',
    'label',
    'hint' => null,
    'required' => false,
    'for' => null,
])

@php($errorName = ltrim((string) preg_replace('/\[([^\]]+)\]/', '.$1', $name), '.'))

<div {{ $attributes->class(['b360-field']) }}>
    <label for="{{ $for ?? $name }}">
        {{ $label }}
        @if ($required)<span class="b360-required" aria-hidden="true">*</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)<small class="b360-field-hint">{{ $hint }}</small>@endif
    @if (isset($errors) && $errors->has($errorName))
        <span class="b360-field-error" role="alert">{{ $errors->first($errorName) }}</span>
    @endif
</div>

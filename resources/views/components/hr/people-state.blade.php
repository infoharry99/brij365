@props([
    'type' => 'empty',
    'title',
    'message',
    'icon' => null,
    'compact' => false,
    'actionUrl' => null,
    'actionLabel' => null,
])

@php
    $states = [
        'empty' => ['icon' => 'fa-inbox', 'role' => 'status'],
        'filtered' => ['icon' => 'fa-filter-circle-xmark', 'role' => 'status'],
        'restricted' => ['icon' => 'fa-lock', 'role' => 'note'],
        'retry' => ['icon' => 'fa-rotate-right', 'role' => 'alert'],
        'conflict' => ['icon' => 'fa-triangle-exclamation', 'role' => 'alert'],
        'error' => ['icon' => 'fa-circle-exclamation', 'role' => 'alert'],
        'warning' => ['icon' => 'fa-triangle-exclamation', 'role' => 'alert'],
        'success' => ['icon' => 'fa-circle-check', 'role' => 'status'],
        'loading' => ['icon' => 'fa-spinner', 'role' => 'status'],
    ];
    $state = $states[$type] ?? $states['empty'];
    $stateType = array_key_exists($type, $states) ? $type : 'empty';
@endphp

<section
    {{ $attributes->class(['people-panel-empty', 'people-state', 'is-'.$stateType, 'is-compact' => $compact]) }}
    role="{{ $state['role'] }}"
    @if (in_array($stateType, ['retry', 'conflict', 'error', 'warning'], true)) tabindex="-1" @endif
    @if ($stateType === 'loading') aria-busy="true" aria-live="polite" @endif
    @if ($stateType === 'success') aria-live="polite" @endif
    data-people-state="{{ $stateType }}"
>
    <i class="fa-solid {{ $icon ?: $state['icon'] }}" aria-hidden="true"></i>
    <strong>{{ $title }}</strong>
    <span>{{ $message }}</span>
    @if ($actionUrl && $actionLabel)
        <a class="people-button" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</section>

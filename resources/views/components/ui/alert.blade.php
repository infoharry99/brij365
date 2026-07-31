@props([
    'tone' => 'success',
    'title' => null,
    'dismissible' => false,
])

<section
    {{ $attributes->class(['blade-alert', 'blade-alert-'.$tone]) }}
    role="{{ in_array($tone, ['danger', 'error'], true) ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="dismissibleAlert" x-show="visible" @endif
>
    <div class="b360-alert-copy">
        @if ($title)<strong>{{ $title }}</strong>@endif
        {{ $slot }}
    </div>
    @if ($dismissible)
        <button type="button" class="b360-alert-dismiss" x-on:click="dismiss" aria-label="Dismiss message">×</button>
    @endif
</section>

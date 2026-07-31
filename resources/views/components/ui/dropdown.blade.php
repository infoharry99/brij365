@props([
    'label',
    'align' => 'right',
])

<div {{ $attributes->class(['b360-dropdown']) }} x-data="togglePanel" x-on:keydown.escape.window="closePanel">
    <button type="button" class="blade-secondary-action" x-ref="trigger" x-on:click="togglePanel" x-bind:aria-expanded="openState" aria-haspopup="menu">
        {{ $label }}
    </button>
    <div class="b360-dropdown-menu is-{{ $align }}" x-ref="panel" x-show="open" x-cloak tabindex="-1" role="menu">
        {{ $slot }}
    </div>
</div>

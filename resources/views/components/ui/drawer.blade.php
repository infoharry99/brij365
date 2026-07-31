@props([
    'id',
    'title',
    'trigger',
    'description' => null,
])

<div x-data="togglePanel" x-on:keydown.escape.window="closePanel">
    <x-ui.action type="button" x-ref="trigger" x-on:click="openPanel" x-bind:aria-expanded="openState" aria-haspopup="dialog" aria-controls="{{ $id }}">
        {{ $trigger }}
    </x-ui.action>
    <div class="b360-modal-layer" x-show="open" x-cloak>
        <button type="button" class="b360-modal-backdrop" x-on:click="closePanel" aria-label="Close {{ $title }}"></button>
        <aside id="{{ $id }}" class="b360-drawer-panel" x-ref="panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
            <header class="b360-modal-head">
                <div>
                    <h2 id="{{ $id }}-title">{{ $title }}</h2>
                    @if ($description)<p>{{ $description }}</p>@endif
                </div>
                <button type="button" class="b360-icon-btn" x-on:click="closePanel" aria-label="Close {{ $title }}">×</button>
            </header>
            <div class="b360-modal-body">{{ $slot }}</div>
        </aside>
    </div>
</div>

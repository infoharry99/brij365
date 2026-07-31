@props(['name'])

<button
    type="button"
    data-tab-key="{{ $name }}"
    x-on:click="selectTab"
    x-bind:aria-selected="activeTab === @js($name)"
    role="tab"
    {{ $attributes }}
>{{ $slot }}</button>

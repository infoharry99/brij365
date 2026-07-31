@props(['name'])

<section x-show="activeTab === @js($name)" x-cloak role="tabpanel" {{ $attributes }}>{{ $slot }}</section>

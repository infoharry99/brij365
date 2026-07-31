@props(['initial'])

<section {{ $attributes }} x-data="tabSet" data-initial-tab="{{ $initial }}">
    {{ $slot }}
</section>

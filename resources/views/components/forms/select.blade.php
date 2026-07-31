@props(['name'])

<select id="{{ $attributes->get('id', $name) }}" name="{{ $name }}" {{ $attributes->except('id')->class(['b360-control']) }}>
    {{ $slot }}
</select>

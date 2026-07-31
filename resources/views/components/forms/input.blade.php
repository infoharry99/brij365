@props(['name', 'type' => 'text'])

<input id="{{ $attributes->get('id', $name) }}" name="{{ $name }}" type="{{ $type }}" {{ $attributes->except('id')->class(['b360-control']) }}>

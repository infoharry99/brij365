@props(['name'])

<textarea id="{{ $attributes->get('id', $name) }}" name="{{ $name }}" {{ $attributes->except('id')->class(['b360-control']) }}>{{ $slot }}</textarea>

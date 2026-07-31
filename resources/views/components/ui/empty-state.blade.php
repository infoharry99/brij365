@props([
    'title',
    'description' => null,
    'icon' => 'fa-box-open',
])

<section {{ $attributes->class(['b360-empty']) }} aria-label="{{ $title }}">
    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    <strong>{{ $title }}</strong>
    @if ($description)<span>{{ $description }}</span>@endif
    @isset($actions)<div class="blade-workspace-actions">{{ $actions }}</div>@endisset
</section>

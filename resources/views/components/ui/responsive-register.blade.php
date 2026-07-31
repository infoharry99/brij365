@props(['label'])

<section {{ $attributes }} aria-label="{{ $label }}">
    <div class="b360-register-desktop blade-dashboard-table-wrap">
        {{ $desktop }}
    </div>
    <div class="b360-register-mobile">
        {{ $mobile }}
    </div>
</section>

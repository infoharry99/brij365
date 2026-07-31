<div
    {{ $attributes->class(['people-workspace', 'is-self-service' => $selfService]) }}
    role="region"
    aria-label="People workspace"
    x-data="peopleWorkspace"
    data-create-open="{{ $openCreate ? '1' : '0' }}"
    x-on:keydown.escape.window="handlePeopleEscape"
    x-on:resize.window="handlePeopleResize"
>
    @unless ($selfService)
        <button
            type="button"
            class="people-rail-toggle"
            x-ref="railTrigger"
            x-on:click="togglePeopleRail"
            x-bind:aria-expanded="railExpanded"
            aria-controls="people-workspace-rail"
        >
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            <span>People workspace</span>
        </button>

        <button
            type="button"
            class="people-rail-backdrop"
            x-show="railOpen"
            x-cloak
            x-on:click="closePeopleRail"
            aria-label="Close People workspace navigation"
        ></button>

        @include('hr.partials.people-workspace-rail', [
            'activePeopleSection' => $active,
            'peopleLinks' => $navigationLinks,
        ])
    @endunless

    <main class="people-main" id="people-main-content">
        <header class="people-page-header">
            <div class="people-page-heading">
                <p class="people-eyebrow">{{ $eyebrow }}</p>
                <h1>{{ $title }}</h1>
                @if ($description)<p>{{ $description }}</p>@endif
            </div>

            @isset($actions)
                <div class="people-page-actions" aria-label="Page actions">{{ $actions }}</div>
            @endisset
        </header>

        {{ $slot }}
    </main>
</div>

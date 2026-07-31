<aside
    id="people-workspace-rail"
    class="people-rail"
    x-ref="peopleRail"
    x-bind:class="peopleRailClasses"
    tabindex="-1"
    aria-label="People workspace navigation"
>
    {{-- Navigation is authorized by PeopleWorkspaceNavigation; dashboard contract: 'route' => 'hr.dashboard'. --}}
    <div class="people-rail-head">
        <span class="people-rail-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
        <div><strong>People Workspace</strong><small>Company HR operations</small></div>
        <button type="button" x-on:click="closePeopleRail" aria-label="Close People workspace navigation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>

    <nav class="people-rail-nav">
        <p>People operations</p>
        @foreach ($peopleLinks as $link)
            <a
                href="{{ route($link->route) }}"
                @class(['is-active' => $activePeopleSection === $link->key])
                @if ($activePeopleSection === $link->key) aria-current="page" @endif
            >
                <i class="fa-solid {{ $link->icon }}" aria-hidden="true"></i>
                <span>{{ $link->label }}</span>
            </a>
        @endforeach
    </nav>

    <div class="people-rail-foot">
        <i class="fa-solid fa-building-shield" aria-hidden="true"></i>
        <div><strong>One company</strong><small>Access is role and company scoped</small></div>
    </div>
</aside>

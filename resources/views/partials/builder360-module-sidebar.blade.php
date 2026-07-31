@php
    use App\Services\Builder360\Builder360Bootstrap;
    use App\Support\Builder360ModuleNavigation;

    $builder360NavigationBootstrap = $builder360NavigationBootstrap
        ?? $bootstrap
        ?? (auth()->check() ? app(Builder360Bootstrap::class)->forUser(auth()->user()) : []);

    $builder360NavigationModules = collect($builder360NavigationBootstrap['modules'] ?? []);
@endphp

<aside class="sidebar blade-dashboard-sidebar" aria-label="Primary module navigation">
    <div class="sb-brand">
        <div class="sb-logo" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 21h18" />
                <path d="M5 21V7l7-4 7 4v14" />
                <path d="M9 21v-8h6v8" />
                <path d="M8 9h.01M12 9h.01M16 9h.01" />
            </svg>
        </div>
        <div>
            <div class="sb-brand-name">Builder360</div>
            <div class="sb-brand-sub">Construction ERP · CRM</div>
        </div>
    </div>

    <div class="sb-search" aria-label="Module search placeholder">
        <span>Search modules</span>
        <kbd>/</kbd>
    </div>

    <nav class="sb-nav blade-sidebar-nav">
        @forelse ($builder360NavigationModules as $group)
            <section class="blade-sidebar-group" aria-labelledby="blade-sidebar-group-{{ \Illuminate\Support\Str::slug($group['group'] ?? 'modules') }}">
                <h2 class="sb-group-label" id="blade-sidebar-group-{{ \Illuminate\Support\Str::slug($group['group'] ?? 'modules') }}">{{ $group['group'] ?? 'Modules' }}</h2>
                @foreach (($group['items'] ?? []) as $item)
                    @php
                        $moduleRoute = $item['route'] ?? $item['slug'] ?? null;
                        $url = Builder360ModuleNavigation::urlFor($moduleRoute, $builder360NavigationBootstrap);
                        $isActive = Builder360ModuleNavigation::isActive($moduleRoute);
                        $moduleName = $item['name'] ?? $item['slug'] ?? 'Module';
                        $moduleInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($moduleName, 0, 1));
                    @endphp

                    @if ($url)
                        <a href="{{ $url }}" class="{{ $isActive ? 'blade-sidebar-link is-active' : 'blade-sidebar-link nav-item' }}" @if ($isActive) aria-current="page" @endif>
                            <span class="ni-ic" aria-hidden="true">{{ $moduleInitial }}</span>
                            <span class="nav-label">{{ $moduleName }}</span>
                        </a>
                    @else
                        <span class="nav-item blade-sidebar-link is-disabled" aria-disabled="true">
                            <span class="ni-ic" aria-hidden="true">{{ $moduleInitial }}</span>
                            <span class="nav-label">{{ $moduleName }}</span>
                        </span>
                    @endif
                @endforeach
            </section>
        @empty
            <p class="faint" style="padding: 12px;">No authorized modules are available for this account.</p>
        @endforelse
    </nav>

    <div class="sb-foot">
        <span class="badge b-accent">Builder360 workspace</span>
    </div>
</aside>

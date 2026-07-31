# Phase 4 — Approved Application Shell Completion

## Outcome

Phase 4 is complete for the server-rendered Builder360 application shell. The approved light visual language remains the default while all global controls now execute through named Laravel web routes and standard forms.

## Delivered

- Authenticated responsive Blade shell at `http://127.0.0.1:8001`.
- Role-correct server navigation with approved local iconography.
- Server-authoritative project and authorized role contexts.
- Working company-scoped Global Search for projects, units, leads, and vouchers.
- Mobile Global Search through the hamburger navigation.
- Session-backed light/dark theme preference without browser storage.
- Recipient-scoped notification badge and route.
- Bottom-left Dashboard, My Profile, and CSRF-protected POST logout.
- Read-only profile workspace with account access, security, and recent activity.
- Immutable DTO boundary for shell navigation, context options, user identity, and counters.
- Immutable profile and search page DTOs.

## Architecture evidence

- Named web routes and Form Requests protect search and theme changes.
- Gates authorize global search and theme changes.
- Thin invokable controllers delegate to one-use-case Actions.
- Intent-named services enforce company scope and preference persistence.
- Blade shell partials do not read the legacy bootstrap array.
- Alpine remains limited to local navigation, disclosure, and form-presentation state.
- No React, Vue, Inertia, Livewire, frontend API calls, or `localStorage` participate in the shell.

## Verification evidence

- Full suite: **706 tests passed, 16,026 assertions**.
- `php artisan builder360:verify --json`: **status ok**, MySQL, single-company, migrations, authorization, assets, queue, cache, storage, scheduler and security checks all ok.
- `npm run build`: passed with local Vite/Tailwind/Alpine assets and local icon fonts.
- Browser: desktop, tablet and mobile widths checked.
- Browser: one active navigation item, no horizontal page overflow, role/project values correct, Global Search drilldown correct, theme state changes correctly, mobile sidebar search visible, no console errors.

## Security and scope decisions

- Search categories are included only when the existing model policy permits `viewAny`.
- Every search query uses `CompanyScopeService`; wildcard users remain inside the configured company.
- Search links include only query parameters accepted by the destination Form Request.
- Theme values are allow-listed to `light` and `dark` and stored in the authenticated session.

## Rollback

Rollback Phase 4 by removing the search and theme routes/controllers/Actions/services/DTOs, restoring the prior decorative search markup and initial-letter navigation markup, and restoring shell partials to the legacy bootstrap array. No database migration or destructive data rollback is required.

## Next phase

Phase 5 builds the dedicated Scoring Logic module with versioned rules, approval/activation/rollback, impact preview, deterministic calculators, score snapshots, recalculation jobs, and full audit history.

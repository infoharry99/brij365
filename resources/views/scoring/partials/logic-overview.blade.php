<section class="logic-readiness-grid" aria-label="Logic Center readiness">
    @foreach ([
        ['Governed variable packs', $page->readiness['variablePacks'], 'Versioned System Settings and statutory packs', 'fa-box-archive', 'is-info'],
        ['Active packs', $page->readiness['activePacks'], 'Effective versions available in the current company scope', 'fa-circle-check', 'is-success'],
        ['Awaiting verification', $page->readiness['unverifiedPacks'], 'Cannot become authoritative for statutory payroll', 'fa-triangle-exclamation', 'is-warning'],
        ['Draft packs', $page->readiness['draftPacks'], 'Maker-checker review remains outstanding', 'fa-pen-ruler', 'is-muted'],
    ] as [$label, $value, $detail, $icon, $tone])
        <article class="logic-readiness-card {{ $tone }}">
            <span class="logic-readiness-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
            <span>{{ $label }}</span>
            <strong>{{ $value }}</strong>
            <small>{{ $detail }}</small>
        </article>
    @endforeach
</section>

@if ($page->readiness['unverifiedPacks'] > 0)
    <div class="logic-guard-notice" role="status">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span><strong>Authoritative calculation guard is active.</strong> Unverified statutory packs can be reviewed and simulated, but cannot affect payroll until official-source evidence and independent approval are recorded.</span>
    </div>
@endif

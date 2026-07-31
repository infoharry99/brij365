@php($activeLifecycleSection = $activeLifecycleSection ?? 'tracker')
<nav class="people-ops-tabs" aria-label="Employee Lifecycle sections">
    <a href="{{ route('hr.lifecycle.index') }}" @class(['is-active' => $activeLifecycleSection === 'tracker']) @if($activeLifecycleSection === 'tracker') aria-current="page" @endif>
        <i class="fa-solid fa-route" aria-hidden="true"></i> Lifecycle tracker
    </a>
    <a href="{{ route('hr.confirmation-cases.index') }}" @class(['is-active' => $activeLifecycleSection === 'confirmation']) @if($activeLifecycleSection === 'confirmation') aria-current="page" @endif>
        <i class="fa-solid fa-user-check" aria-hidden="true"></i> Confirmation
    </a>
    <a href="{{ route('hr.separation-settlements.index') }}" @class(['is-active' => $activeLifecycleSection === 'separation']) @if($activeLifecycleSection === 'separation') aria-current="page" @endif>
        <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Full &amp; Final
    </a>
    <a href="{{ route('hr.exit-interviews.index') }}" @class(['is-active' => $activeLifecycleSection === 'exit']) @if($activeLifecycleSection === 'exit') aria-current="page" @endif>
        <i class="fa-regular fa-comments" aria-hidden="true"></i> Exit interviews
    </a>
</nav>

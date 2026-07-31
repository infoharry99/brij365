@extends('layouts.builder360-classic')

@php
    $businessTabs = [
        'business' => 'All business rules', 'lead' => 'Lead Scoring',
        'confirmation' => 'Employee Confirmation', 'recruitment' => 'Recruitment Interview',
        'vendor' => 'Vendor Performance', 'project' => 'Project Health',
        'customer-satisfaction' => 'Customer Satisfaction', 'exit-feedback' => 'Exit Feedback',
    ];
    $auditTabs = [
        'audit' => 'Version audit',
        'score-history' => 'Score History', 'rule-history' => 'Rule Change History',
    ];
    $ruleViews = ['overview', 'business', 'lead', 'performance', 'confirmation', 'recruitment', 'vendor', 'project', 'customer-satisfaction', 'exit-feedback', 'rule-history', 'audit'];
    $snapshotViews = ['overview', 'business', 'lead', 'performance', 'confirmation', 'recruitment', 'vendor', 'project', 'customer-satisfaction', 'exit-feedback', 'score-history', 'audit'];
    $showRuleRegister = in_array($page->view, $ruleViews, true);
    $showScoreHistory = in_array($page->view, $snapshotViews, true);
    $showRecalculation = in_array($page->view, ['overview', 'audit', 'rule-history'], true);
    $showRuleCreate = $page->canCreate && in_array($page->view, ['overview', 'business', 'performance', 'lead', 'confirmation', 'recruitment', 'vendor', 'project', 'customer-satisfaction', 'exit-feedback'], true);
@endphp

@section('title', $page->title.' | Builder360')

@section('content')
    <div class="logic-center-workspace">
    <x-ui.page-header eyebrow="Scoring Logic" :title="$page->title" :description="$page->description">
        @if ($showRuleCreate)
            <x-slot:actions>
                <x-ui.modal id="create-scoring-rule" title="New scoring rule draft" trigger="New rule draft" description="Start from a safe structured Builder360 scoring template." trigger-variant="primary">
                    <form method="POST" action="{{ route('scoring.rules.store') }}" class="blade-form-grid">
                        @csrf
                        <x-forms.field name="rule_key" label="Scoring area" required>
                            <x-forms.select name="rule_key" required>
                                <option value="">Select scoring area</option>
                                @foreach ($page->ruleTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('rule_key') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </x-forms.field>
                        <x-forms.field name="name" label="Rule name" required>
                            <x-forms.input name="name" :value="old('name')" maxlength="140" required />
                        </x-forms.field>
                        <x-forms.field name="effective_at" label="Planned effective date">
                            <x-forms.input name="effective_at" type="datetime-local" :value="old('effective_at')" />
                        </x-forms.field>
                        <x-forms.field name="change_reason" label="Change reason" hint="Required for rule history and approval." required>
                            <x-forms.textarea name="change_reason" rows="4" maxlength="2000" required>{{ old('change_reason') }}</x-forms.textarea>
                        </x-forms.field>
                        <div class="blade-form-actions">
                            <x-ui.action type="submit" variant="primary">Create draft</x-ui.action>
                        </div>
                    </form>
                </x-ui.modal>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <nav class="logic-center-nav" aria-label="People Logic Center sections">
        @foreach ($page->sections as $section)
            <a href="{{ $section->url }}" class="logic-center-nav-item{{ $section->active ? ' is-active' : '' }}" @if ($section->active) aria-current="page" @endif>
                <i class="fa-solid {{ $section->icon }}" aria-hidden="true"></i>
                <span><strong>{{ $section->label }}</strong><small>{{ $section->description }}</small></span>
            </a>
        @endforeach
    </nav>

    @if (in_array($page->view, array_keys($businessTabs), true))
        <nav class="b360-scoring-tabs" aria-label="Business scoring areas">
            @foreach ($businessTabs as $key => $label)
                <a href="{{ route('scoring.index', ['view' => $key]) }}" class="{{ $page->view === $key ? 'is-active' : '' }}" @if ($page->view === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    @elseif (in_array($page->view, array_keys($auditTabs), true))
        <nav class="b360-scoring-tabs" aria-label="Scoring audit views">
            @foreach ($auditTabs as $key => $label)
                <a href="{{ route('scoring.index', ['view' => $key]) }}" class="{{ $page->view === $key ? 'is-active' : '' }}" @if ($page->view === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    @if ($page->view === 'overview')
        @include('scoring.partials.logic-overview')
        @include('scoring.partials.logic-variable-packs')
    @elseif ($page->view === 'statutory')
        @include('scoring.partials.logic-statutory-pack-editor')
        @include('scoring.partials.logic-variable-packs')
    @elseif ($page->view === 'roster')
        @include('scoring.partials.logic-roster-pack-editor')
        @include('scoring.partials.logic-variable-packs')
    @elseif ($page->view === 'audit')
        @include('scoring.partials.logic-variable-packs')
    @elseif ($page->view === 'simulation')
        @include('scoring.partials.logic-simulation')
    @endif

    @if ($showRuleRegister)
    <section class="b360-stat-grid" aria-label="Scoring metrics">
        @foreach ([['Rules', $page->counts['rules']], ['Active', $page->counts['active']], ['Awaiting approval', $page->counts['pending']], ['Score snapshots', $page->counts['snapshots']]] as [$label, $value])
            <article class="b360-stat-card">
                <span class="b360-card-icon b-violet"><i class="fa-solid fa-chart-simple"></i></span>
                <span class="b360-stat-label">{{ $label }}</span>
                <strong>{{ $value }}</strong>
                <small>Available to your role</small>
            </article>
        @endforeach
    </section>

    <x-ui.card id="rule-register" title="Rule register" eyebrow="Versions" meta="{{ count($page->rules) }} records">
        @if (count($page->rules) === 0)
            <x-ui.empty-state title="No scoring rules found" description="Create a structured rule draft or change the selected scoring area." icon="fa-sliders" />
        @else
            <x-ui.responsive-register label="Scoring rules">
                <x-slot:desktop>
                    <table class="blade-data-table">
                        <thead><tr><th>Rule</th><th>Version</th><th>Status</th><th>Effective</th><th>Created by</th><th>Checksum</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($page->rules as $rule)
                                <tr>
                                    <td><strong>{{ $rule->name }}</strong><small>{{ $rule->ruleKey }}</small></td>
                                    <td>v{{ $rule->version }}</td>
                                    <td><x-ui.badge>{{ $rule->status }}</x-ui.badge></td>
                                    <td>{{ $rule->effectiveAt }}</td>
                                    <td>{{ $rule->createdBy }}</td>
                                    <td><code>{{ $rule->checksum }}</code></td>
                                    <td><div class="blade-inline-actions">
                                        <x-ui.action :href="route('scoring.rules.show', $rule->id)">View</x-ui.action>
                                        @if ($rule->canUpdate)
                                            <x-ui.action :href="route('scoring.rules.edit', $rule->id)">Edit</x-ui.action>
                                        @endif
                                        @if ($rule->canClone)
                                            <x-ui.modal id="clone-rule-{{ $rule->id }}" title="Clone rule version" trigger="Clone" description="Create a new draft from version {{ $rule->version }}.">
                                                <form method="POST" action="{{ route('scoring.rules.clone', $rule->id) }}" class="blade-form-grid">
                                                    @csrf
                                                    <x-forms.field name="clone_reason_{{ $rule->id }}" label="Change reason" required>
                                                        <x-forms.textarea name="change_reason" id="clone_reason_{{ $rule->id }}" rows="3" maxlength="2000" required></x-forms.textarea>
                                                    </x-forms.field>
                                                    <div class="blade-form-actions"><x-ui.action type="submit" variant="primary">Create clone draft</x-ui.action></div>
                                                </form>
                                            </x-ui.modal>
                                            @if (! in_array($rule->status, ['Draft', 'Validated'], true))
                                                <x-ui.modal id="rollback-rule-{{ $rule->id }}" title="Prepare rollback draft" trigger="Rollback" description="Copy version {{ $rule->version }} into a new draft. Activation still requires validation and approval.">
                                                    <form method="POST" action="{{ route('scoring.rules.rollback', $rule->id) }}" class="blade-form-grid">
                                                        @csrf
                                                        <x-forms.field name="rollback_reason_{{ $rule->id }}" label="Rollback reason" required>
                                                            <x-forms.textarea name="change_reason" id="rollback_reason_{{ $rule->id }}" rows="3" maxlength="2000" required></x-forms.textarea>
                                                        </x-forms.field>
                                                        <div class="blade-form-actions"><x-ui.action type="submit" variant="primary">Create rollback draft</x-ui.action></div>
                                                    </form>
                                                </x-ui.modal>
                                            @endif
                                        @endif
                                        @if ($rule->canReject)
                                            <x-ui.modal id="reject-rule-{{ $rule->id }}" title="Return rule for correction" trigger="Reject" description="The creator can edit and resubmit this version.">
                                                <form method="POST" action="{{ route('scoring.rules.reject', $rule->id) }}" class="blade-form-grid">
                                                    @csrf @method('PATCH')
                                                    <x-forms.field name="reject_reason_{{ $rule->id }}" label="Rejection reason" required>
                                                        <x-forms.textarea name="reason" id="reject_reason_{{ $rule->id }}" rows="3" maxlength="2000" required></x-forms.textarea>
                                                    </x-forms.field>
                                                    <div class="blade-form-actions"><x-ui.action type="submit" variant="danger">Return for correction</x-ui.action></div>
                                                </form>
                                            </x-ui.modal>
                                        @endif
                                        @if ($rule->canRetire)
                                            <x-ui.modal id="retire-rule-{{ $rule->id }}" title="Retire active rule" trigger="Retire" description="New calculations will require another approved active version.">
                                                <form method="POST" action="{{ route('scoring.rules.retire', $rule->id) }}" class="blade-form-grid">
                                                    @csrf @method('PATCH')
                                                    <x-forms.field name="retire_reason_{{ $rule->id }}" label="Retirement reason" required>
                                                        <x-forms.textarea name="reason" id="retire_reason_{{ $rule->id }}" rows="3" maxlength="2000" required></x-forms.textarea>
                                                    </x-forms.field>
                                                    <div class="blade-form-actions"><x-ui.action type="submit" variant="danger">Retire rule</x-ui.action></div>
                                                </form>
                                            </x-ui.modal>
                                        @endif
                                        @if ($rule->canRecalculate)
                                            <form method="POST" action="{{ route('scoring.rules.recalculate', $rule->id) }}">
                                                @csrf
                                                <x-ui.action type="submit">Recalculate</x-ui.action>
                                            </form>
                                        @endif
                                        @foreach ([
                                            [$rule->canValidate, 'scoring.rules.validate', 'Validate'],
                                            [$rule->canSubmit, 'scoring.rules.submit', 'Submit'],
                                            [$rule->canApprove, 'scoring.rules.approve', 'Approve'],
                                            [$rule->canActivate, 'scoring.rules.activate', 'Activate'],
                                        ] as [$allowed, $routeName, $label])
                                            @if ($allowed)
                                                <form method="POST" action="{{ route($routeName, $rule->id) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-ui.action type="submit">{{ $label }}</x-ui.action>
                                                </form>
                                            @endif
                                        @endforeach
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:desktop>
                <x-slot:mobile>
                    <div class="b360-mobile-register">
                        @foreach ($page->rules as $rule)
                            <article><strong>{{ $rule->name }}</strong><span>v{{ $rule->version }} &middot; {{ $rule->status }}</span><small>{{ $rule->effectiveAt }}</small></article>
                        @endforeach
                    </div>
                </x-slot:mobile>
            </x-ui.responsive-register>
        @endif
    </x-ui.card>
    @endif

    @if ($showScoreHistory || $showRecalculation)
    <div class="b360-dashboard-grid">
        @if ($showScoreHistory)
        <x-ui.card title="Recent score history" meta="{{ count($page->snapshots) }} records">
            @forelse ($page->snapshots as $snapshot)
                <div class="b360-data-row">
                    <span><strong>{{ $snapshot->subject }}</strong><small>{{ $snapshot->ruleName }} &middot; v{{ $snapshot->ruleVersion }}@if ($snapshot->override) &middot; Overridden @endif</small></span>
                    <span class="blade-inline-actions"><em>{{ $snapshot->score }} &middot; {{ $snapshot->band }}</em>
                        @if ($snapshot->canOverride)
                            <x-ui.modal id="override-score-{{ $snapshot->id }}" title="Override calculated score" trigger="Override" description="The original score and calculation evidence will remain in score history.">
                                <form method="POST" action="{{ route('scoring.snapshots.override', $snapshot->id) }}" class="blade-form-grid">
                                    @csrf
                                    <x-forms.field name="override_score_{{ $snapshot->id }}" label="Override score" required><x-forms.input name="score" type="number" step="0.01" min="0" max="100" required /></x-forms.field>
                                    <x-forms.field name="override_reason_{{ $snapshot->id }}" label="Override reason" required><x-forms.textarea name="reason" id="override_reason_{{ $snapshot->id }}" rows="3" minlength="12" maxlength="2000" required></x-forms.textarea></x-forms.field>
                                    <div class="blade-form-actions"><x-ui.action type="submit" variant="primary">Save override</x-ui.action></div>
                                </form>
                            </x-ui.modal>
                        @endif
                    </span>
                </div>
            @empty
                <x-ui.empty-state title="No score history" description="Calculated score snapshots will appear here." icon="fa-clock" />
            @endforelse
        </x-ui.card>
        @endif

        @if ($showRecalculation)
        <x-ui.card title="Recalculation status" meta="{{ count($page->recalculationRuns) }} runs">
            @forelse ($page->recalculationRuns as $run)
                <div class="b360-data-row"><span><strong>{{ $run['rule'] }}</strong><small>{{ $run['processed'] }}/{{ $run['total'] }} processed</small></span><em>{{ $run['status'] }}</em></div>
            @empty
                <x-ui.empty-state title="No recalculation runs" description="Activation recalculation progress and failures will appear here." icon="fa-rotate" />
            @endforelse
            @if (count($page->recalculationFailures) > 0)
                <h3 class="b360-section-heading">Recent recalculation failures</h3>
                @foreach ($page->recalculationFailures as $failure)
                    <div class="b360-data-row"><span><strong>{{ $failure['subject'] }}</strong><small>{{ $failure['rule'] }}</small></span><em>{{ $failure['message'] }}</em></div>
                @endforeach
            @endif
        </x-ui.card>
        @endif
    </div>
    @endif
    </div>
@endsection

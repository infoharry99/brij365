@extends('layouts.builder360-classic')

@section('title', 'Lead Qualification - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="lead-qualification-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Sales and CRM</p>
                <h1 id="lead-qualification-title">Lead Qualification</h1>
                <p>
                    Workspace for lead quality scoring, condition-based qualification,
                    score-band routing, checks, activity history and company-level qualification records.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Workspace actions">
                <a href="{{ route('builder360.dashboard') }}">Dashboard</a>
                <a href="{{ route('crm.lead-qualifications.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <section class="blade-alert blade-alert-success" role="status">
                {{ session('status') }}
            </section>
        @endif

        @if ($errors->any())
            <section class="blade-alert blade-alert-error" role="alert">
                <strong>Lead qualification was not saved.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Rule source</span>
                        <h2>Quality score configuration</h2>
                    </div>
                    <small>
                        {{ $rules['source'] ?? 'application_default' }}
                        @if (! empty($rules['version']))
                            · v{{ $rules['version'] }}
                        @endif
                    </small>
                </div>

                <div class="blade-score-grid">
                    @foreach (($rules['criteria'] ?? []) as $criterionKey => $criterion)
                        <div class="blade-score-card">
                            <strong>{{ $criterion['label'] ?? str($criterionKey)->replace('_', ' ')->title() }}</strong>
                            <span>Max {{ $criterion['max_points'] ?? 0 }} points</span>
                            <ul>
                                @foreach (($criterion['options'] ?? []) as $option)
                                    <li>{{ $option['label'] ?? $option['value'] }}: {{ $option['points'] ?? 0 }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="blade-band-list" aria-label="Score bands">
                    @foreach (($rules['bands'] ?? []) as $band)
                        <div>
                            <strong>{{ $band['label'] ?? 'Score Band' }}</strong>
                            <span>{{ $band['min_score'] ?? 0 }}+ points routes to {{ $band['status_hint'] ?? 'nurture' }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($canManageScoring)
                    <p class="blade-workspace-note">
                        Criteria, conditions, weights and score bands are managed in
                        <a href="{{ $scoringUrl }}">Scoring Logic</a> through its approval-controlled rule lifecycle.
                    </p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Run lead qualification</h2>
                    </div>
                    <small>{{ $canQualify ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canQualify)
                    <form method="POST" action="{{ route('crm.lead-qualifications.store') }}" class="blade-form-grid">
                        @csrf

                        <label>
                            Lead
                            <select name="lead_id" required>
                                <option value="">Select lead</option>
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected((string) old('lead_id', $filters['lead_id'] ?? '') === (string) $lead->id)>
                                        {{ $lead->lead_code }} · {{ $lead->customer?->name ?? 'Customer pending' }} · {{ $lead->project?->code ?? 'No project' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            Result status
                            <select name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'qualified') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @foreach (($rules['criteria'] ?? []) as $criterionKey => $criterion)
                            <label>
                                {{ $criterion['label'] ?? str($criterionKey)->replace('_', ' ')->title() }}
                                <select name="quality_conditions[{{ $criterionKey }}]" required>
                                    <option value="">Select condition</option>
                                    @foreach (($criterion['options'] ?? []) as $option)
                                        <option value="{{ $option['value'] }}" @selected(old("quality_conditions.{$criterionKey}") === ($option['value'] ?? null))>
                                            {{ $option['label'] ?? $option['value'] }} · {{ $option['points'] ?? 0 }} pts
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach

                        <label>
                            Preferred configuration
                            <input type="text" name="preferred_configuration" value="{{ old('preferred_configuration') }}" maxlength="80" placeholder="2 BHK, 3 BHK, duplex">
                        </label>

                        <label>
                            Verified budget min
                            <input type="number" name="verified_budget_min" value="{{ old('verified_budget_min') }}" min="0" step="0.01">
                        </label>

                        <label>
                            Verified budget max
                            <input type="number" name="verified_budget_max" value="{{ old('verified_budget_max') }}" min="0" step="0.01">
                        </label>

                        <label>
                            Expected booking date
                            <input type="date" name="expected_booking_date" value="{{ old('expected_booking_date') }}">
                        </label>

                        <label class="blade-form-wide">
                            Decision notes
                            <textarea name="decision_notes" required maxlength="5000" rows="4" placeholder="Record verification notes, fitment reason, next action and routing justification.">{{ old('decision_notes') }}</textarea>
                        </label>

                        <button type="submit" class="blade-primary-action">Save qualification</button>
                    </form>
                @else
                    <p class="blade-workspace-note">You can view qualification records, but your role cannot create new qualifications.</p>
                @endif
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Register</span>
                    <h2>Qualification records</h2>
                </div>
                <small>{{ $qualifications->total() }} record(s)</small>
            </div>

            <form method="GET" action="{{ route('crm.lead-qualifications.index') }}" class="blade-filter-grid">
                <label>
                    Lead
                    <select name="lead_id">
                        <option value="">All leads</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((string) ($filters['lead_id'] ?? '') === (string) $lead->id)>
                                {{ $lead->lead_code }} · {{ $lead->customer?->name ?? 'Customer pending' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Minimum score
                    <input type="number" name="min_score" min="0" max="100" value="{{ $filters['min_score'] ?? '' }}">
                </label>
                <label>
                    Expected from
                    <input type="date" name="expected_from" value="{{ $filters['expected_from'] ?? '' }}">
                </label>
                <label>
                    Expected to
                    <input type="date" name="expected_to" value="{{ $filters['expected_to'] ?? '' }}">
                </label>
                <button type="submit" class="blade-secondary-action">Apply filters</button>
            </form>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Qualification</th>
                            <th scope="col">Lead</th>
                            <th scope="col">Score</th>
                            <th scope="col">Band</th>
                            <th scope="col">Status</th>
                            <th scope="col">Budget</th>
                            <th scope="col">Expected booking</th>
                            <th scope="col">Qualified by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qualifications as $qualification)
                            @php
                                $qualityScore = is_array($qualification->metadata) ? ($qualification->metadata['quality_score'] ?? []) : [];
                                $band = is_array($qualityScore) ? ($qualityScore['band'] ?? []) : [];
                                $currentScore = $leadScores[$qualification->lead_id] ?? null;
                                $isCurrentQualificationScore = $currentScore
                                    && (int) ($currentScore->metadata['qualification_id'] ?? 0) === (int) $qualification->id;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $qualification->qualification_number }}</strong>
                                    <span>{{ $qualification->qualified_at?->format('d M Y H:i') ?? 'Pending timestamp' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $qualification->lead?->lead_code ?? 'Lead missing' }}</strong>
                                    <span>{{ $qualification->lead?->customer?->name ?? 'Customer pending' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $isCurrentQualificationScore ? $currentScore->score : $qualification->score }}</strong>
                                    @if ($isCurrentQualificationScore)
                                        <span>Rule v{{ $currentScore->ruleVersion }}</span>
                                    @endif
                                </td>
                                <td>{{ $isCurrentQualificationScore ? str($currentScore->band)->headline() : ($band['label'] ?? 'Unclassified') }}</td>
                                <td>{{ str($qualification->status)->headline() }}</td>
                                <td>
                                    @if ($qualification->verified_budget_min || $qualification->verified_budget_max)
                                        {{ number_format((float) $qualification->verified_budget_min, 2) }}
                                        -
                                        {{ number_format((float) $qualification->verified_budget_max, 2) }}
                                    @else
                                        Not verified
                                    @endif
                                </td>
                                <td>{{ $qualification->expected_booking_date?->format('d M Y') ?? 'Not set' }}</td>
                                <td>{{ $qualification->qualifiedBy?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No lead qualification records match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="blade-pagination">
                {{ $qualifications->links() }}
            </div>
        </section>
    </div>
@endsection

<section class="logic-simulation-grid" aria-label="Available non-mutating simulations">
    <article class="logic-simulation-card logic-simulation-card-wide">
        <span class="logic-simulation-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
        <div>
            <h2>Performance score simulation</h2>
            <p>Enter normalized criterion scores from 0 to 100 and preview the selected governed formula version. Simulations remain separate from employee reviews and score evidence.</p>
        </div>

        @if (! $page->capabilities['managePerformance'])
            <span class="logic-restricted-state">Your role cannot run performance simulations.</span>
        @elseif (count($page->performanceSimulationRules) === 0)
            <span class="logic-restricted-state is-warning">No Employee Performance rule version is available in your authorized company scope.</span>
        @else
            <div class="logic-simulation-pack-list">
                @foreach ($page->performanceSimulationRules as $rule)
                    @php($performanceRuleOpen = (int) old('performance_simulation_rule_id') === $rule->id || (int) data_get($performanceSimulation ?? [], 'rule_id') === $rule->id)
                    <details class="logic-simulation-pack" @if ($performanceRuleOpen) open @endif>
                        <summary>
                            <span>
                                <strong>{{ $rule->name }}</strong>
                                <small>Version {{ $rule->version }} &middot; {{ $rule->status }}</small>
                            </span>
                            <x-ui.badge tone="neutral">{{ count($rule->criteria) }} criteria</x-ui.badge>
                        </summary>
                        <form method="POST" action="{{ route('scoring.performance-simulations.store', $rule->id) }}" class="logic-simulation-form">
                            @csrf
                            <input type="hidden" name="performance_simulation_rule_id" value="{{ $rule->id }}">

                            <div class="logic-performance-criteria" aria-label="Normalized performance criterion scores">
                                @foreach ($rule->criteria as $criterion)
                                    @php($criterionErrorKey = 'criterion_scores.'.$criterion['key'])
                                    @php($criterionRequired = $criterion['required'] || $criterion['missing_data_behavior'] === 'block')
                                    <div class="logic-performance-criterion">
                                        <div class="logic-performance-criterion-meta">
                                            <label for="performance_criterion_{{ $rule->id }}_{{ $criterion['key'] }}">
                                                {{ $criterion['label'] }}
                                                @if ($criterionRequired)<span aria-hidden="true">*</span>@endif
                                            </label>
                                            <small>{{ number_format($criterion['weight'], 2) }}% weight</small>
                                        </div>
                                        <input
                                            class="b360-control @error($criterionErrorKey) is-invalid @enderror"
                                            id="performance_criterion_{{ $rule->id }}_{{ $criterion['key'] }}"
                                            name="criterion_scores[{{ $criterion['key'] }}]"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            inputmode="decimal"
                                            value="{{ old($criterionErrorKey) }}"
                                            placeholder="0-100"
                                            @if ($criterionRequired) required @endif
                                            @error($criterionErrorKey) aria-invalid="true" aria-describedby="performance_criterion_error_{{ $rule->id }}_{{ $criterion['key'] }}" @enderror
                                        >
                                        @error($criterionErrorKey)
                                            <span class="blade-field-error" id="performance_criterion_error_{{ $rule->id }}_{{ $criterion['key'] }}" role="alert">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>

                            @error('criterion_scores')
                                <p class="blade-field-error" role="alert">{{ $message }}</p>
                            @enderror

                            <p class="logic-simulation-guard"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> This preview cannot create or update a performance review, scoring snapshot, final rating, PIP, promotion, bonus, or training decision.</p>
                            <div class="blade-form-actions">
                                <x-ui.action type="submit" variant="primary">Run performance simulation</x-ui.action>
                                <x-ui.action :href="route('scoring.index', ['view' => 'performance'])">Open performance rules</x-ui.action>
                            </div>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </article>

    <article class="logic-simulation-card logic-simulation-card-wide">
        <span class="logic-simulation-icon"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></span>
        <div>
            <h2>Statutory payroll impact</h2>
            <p>Run deterministic what-if calculations against a governed draft or active pack. Every result is non-authoritative and never creates or changes payroll, attendance, or statutory records.</p>
        </div>

        @if (! $page->capabilities['simulateStatutory'])
            <span class="logic-restricted-state">Your role cannot run statutory simulations.</span>
        @elseif (count($page->variablePacks) === 0)
            <span class="logic-restricted-state is-warning">No governed statutory pack is available in your authorized company scope.</span>
        @else
            <div class="logic-simulation-pack-list">
                @foreach ($page->variablePacks as $pack)
                    <details class="logic-simulation-pack" @if (old('simulation_setting_id') == $pack->id) open @endif>
                        <summary>
                            <span><strong>{{ $pack->label }}</strong><small>{{ $pack->settingKey }} · v{{ $pack->version }} · {{ $pack->status }}</small></span>
                            <x-ui.badge :tone="$pack->verified ? 'success' : 'warning'">{{ $pack->verified ? 'Source verified' : 'Not payroll-authoritative' }}</x-ui.badge>
                        </summary>
                        <form method="POST" action="{{ route('hr.compliance-rule-settings.simulate', $pack->id) }}" class="logic-simulation-form">
                            @csrf
                            <input type="hidden" name="return_to" value="logic_center">
                            <input type="hidden" name="simulation_setting_id" value="{{ $pack->id }}">

                            <x-forms.field name="simulation_state_{{ $pack->id }}" label="Employee statutory state" hint="Use the governed work-location state code, for example MH." required>
                                <x-forms.input name="statutory_state" id="simulation_state_{{ $pack->id }}" :value="old('statutory_state')" maxlength="8" required />
                            </x-forms.field>

                            <div class="logic-simulation-components" aria-label="Earnings components in rupees">
                                <span class="logic-field-label">Component inputs (₹)</span>
                                @foreach ([['BASIC', ''], ['', ''], ['', '']] as $index => [$defaultCode, $defaultAmount])
                                    <div class="logic-simulation-component-row">
                                        <label class="sr-only" for="simulation_code_{{ $pack->id }}_{{ $index }}">Component {{ $index + 1 }} code</label>
                                        <input class="b360-control" id="simulation_code_{{ $pack->id }}_{{ $index }}" name="component_codes[]" value="{{ old('component_codes.'.$index, $defaultCode) }}" placeholder="Component code" @if ($index === 0) required @endif>
                                        <label class="sr-only" for="simulation_amount_{{ $pack->id }}_{{ $index }}">Component {{ $index + 1 }} amount in rupees</label>
                                        <input class="b360-control" id="simulation_amount_{{ $pack->id }}_{{ $index }}" name="component_amounts[]" value="{{ old('component_amounts.'.$index, $defaultAmount) }}" inputmode="decimal" placeholder="Amount in rupees" @if ($index === 0) required @endif>
                                    </div>
                                @endforeach
                            </div>

                            <div class="logic-simulation-context">
                                <x-forms.field name="simulation_employment_type_{{ $pack->id }}" label="Employment type" hint="Required only when this pack limits population.">
                                    <x-forms.input name="employee_context[employment_type]" id="simulation_employment_type_{{ $pack->id }}" :value="old('employee_context.employment_type')" maxlength="100" />
                                </x-forms.field>
                                <x-forms.field name="simulation_department_{{ $pack->id }}" label="Department">
                                    <x-forms.input name="employee_context[department]" id="simulation_department_{{ $pack->id }}" :value="old('employee_context.department')" maxlength="160" />
                                </x-forms.field>
                            </div>

                            <p class="logic-simulation-guard"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Simulation remains non-authoritative even when the selected pack is active. Payroll uses only independently verified, approved, effective packs and finalized attendance.</p>
                            <div class="blade-form-actions"><x-ui.action type="submit" variant="primary">Run non-authoritative simulation</x-ui.action></div>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </article>

    <article class="logic-simulation-card logic-simulation-card-wide">
        <span class="logic-simulation-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
        <div><h2>Roster generation impact</h2><p>Preview rotations, conflicts, rest-period checks and coverage without publishing or changing attendance.</p></div>
        @if (! $page->capabilities['manageRosters'])
            <span class="logic-restricted-state">Your role cannot run roster impact simulations.</span>
        @elseif (count($page->rosterSimulationRules) === 0)
            <span class="logic-restricted-state is-warning">No active attendance rotation is available in your authorized company scope.</span>
        @else
            <div class="logic-simulation-pack-list">
                @foreach ($page->rosterSimulationRules as $rotation)
                    @php($rosterRuleOpen = (int) old('attendance_rotation_rule_id') === $rotation->id || (int) data_get($rosterSimulation ?? [], 'rotation_rule_id') === $rotation->id)
                    <details class="logic-simulation-pack" @if ($rosterRuleOpen) open @endif>
                        <summary>
                            <span>
                                <strong>{{ $rotation->name }}</strong>
                                <small>{{ $rotation->employeeName }} ({{ $rotation->employeeCode }}) &middot; {{ $rotation->cycleDays }}-day cycle</small>
                            </span>
                            <x-ui.badge tone="neutral">{{ str($rotation->status)->headline() }}</x-ui.badge>
                        </summary>
                        <form method="POST" action="{{ route('scoring.roster-simulations.store', $rotation->id) }}" class="logic-simulation-form">
                            @csrf
                            <input type="hidden" name="attendance_rotation_rule_id" value="{{ $rotation->id }}">
                            <div class="logic-simulation-context">
                                <x-forms.field name="roster_simulation_start_{{ $rotation->id }}" label="Preview from" hint="Anchor: {{ $rotation->anchorDate }}" required>
                                    <x-forms.input type="date" name="start_date" id="roster_simulation_start_{{ $rotation->id }}" :value="old('attendance_rotation_rule_id') == $rotation->id ? old('start_date') : now()->toDateString()" required />
                                </x-forms.field>
                                <x-forms.field name="roster_simulation_end_{{ $rotation->id }}" label="Preview through" hint="Maximum governed horizon: {{ $rotation->generationHorizonDays }} days" required>
                                    <x-forms.input type="date" name="end_date" id="roster_simulation_end_{{ $rotation->id }}" :value="old('attendance_rotation_rule_id') == $rotation->id ? old('end_date') : now()->addDays(min(13, $rotation->generationHorizonDays - 1))->toDateString()" required />
                                </x-forms.field>
                            </div>
                            @if (old('attendance_rotation_rule_id') == $rotation->id)
                                @error('attendance_rotation_rule_id')<p class="blade-field-error" role="alert">{{ $message }}</p>@enderror
                                @error('start_date')<p class="blade-field-error" role="alert">{{ $message }}</p>@enderror
                                @error('end_date')<p class="blade-field-error" role="alert">{{ $message }}</p>@enderror
                            @endif
                            <p class="logic-simulation-guard"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> This preview reads effective rule packs and published schedules but cannot create, publish, lock, or change a roster, attendance record, payable day, or payroll input.</p>
                            <div class="blade-form-actions">
                                <x-ui.action type="submit" variant="primary">Run roster simulation</x-ui.action>
                                <x-ui.action :href="route('scoring.index', ['view' => 'roster'])">Open roster rules</x-ui.action>
                            </div>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </article>
</section>

@if ($page->capabilities['managePerformance'] && is_array($performanceSimulation ?? null))
    @php($performanceComponents = (array) data_get($performanceSimulation, 'component_scores', []))
    <x-ui.card id="performance-simulation-result" title="Non-authoritative performance simulation" eyebrow="What-if result" meta="{{ data_get($performanceSimulation, 'rule_name') }} &middot; v{{ data_get($performanceSimulation, 'rule_version') }} &middot; {{ str((string) data_get($performanceSimulation, 'rule_status'))->headline() }}">
        <div class="logic-simulation-result-guard" role="status">
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            <span><strong>This result cannot affect an employee review.</strong> It mutated {{ data_get($performanceSimulation, 'mutated_records', 0) }} records and is retained only for this response.</span>
        </div>
        <section class="logic-simulation-result-grid" aria-label="Performance simulation result">
            @foreach ([
                ['Calculated score', data_get($performanceSimulation, 'total_score', '0.00').'/100'],
                ['Rating band', data_get($performanceSimulation, 'band_label', 'Not assigned')],
                ['Passing threshold', data_get($performanceSimulation, 'passing') ? 'Met' : 'Not met'],
                ['PIP threshold', data_get($performanceSimulation, 'pip_recommended') ? 'Triggered' : 'Not triggered'],
            ] as [$label, $value])
                <article><span>{{ $label }}</span><strong>{{ $value }}</strong></article>
            @endforeach
        </section>

        <x-ui.responsive-register label="Performance formula trace">
            <x-slot:desktop>
                <table class="blade-data-table">
                    <caption class="sr-only">Performance score calculation trace for the selected rule version</caption>
                    <thead><tr><th scope="col">Criterion</th><th scope="col">Input</th><th scope="col">Applied weight</th><th scope="col">Normalized</th><th scope="col">Contribution</th></tr></thead>
                    <tbody>
                        @forelse ($performanceComponents as $criterionKey => $component)
                            <tr>
                                <td><strong>{{ data_get($component, 'label', str((string) $criterionKey)->headline()) }}</strong><small>{{ $criterionKey }}</small></td>
                                <td>{{ number_format((float) data_get($performanceSimulation, 'criterion_scores.'.$criterionKey, 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($performanceSimulation, 'applied_weights.'.$criterionKey, 0), 2) }}%</td>
                                <td>{{ number_format((float) data_get($component, 'normalized_score', 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($component, 'weighted_contribution', 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No criterion calculation lines were returned.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-slot:desktop>
            <x-slot:mobile>
                <div class="b360-mobile-register">
                    @forelse ($performanceComponents as $criterionKey => $component)
                        <article>
                            <strong>{{ data_get($component, 'label', str((string) $criterionKey)->headline()) }}</strong>
                            <span>Input {{ number_format((float) data_get($performanceSimulation, 'criterion_scores.'.$criterionKey, 0), 2) }} &middot; Weight {{ number_format((float) data_get($performanceSimulation, 'applied_weights.'.$criterionKey, 0), 2) }}%</span>
                            <small>Normalized {{ number_format((float) data_get($component, 'normalized_score', 0), 2) }} &middot; Contribution {{ number_format((float) data_get($component, 'weighted_contribution', 0), 2) }}</small>
                        </article>
                    @empty
                        <x-ui.empty-state title="No calculation lines" description="The selected rule returned no criterion calculations." icon="fa-circle-info" />
                    @endforelse
                </div>
            </x-slot:mobile>
        </x-ui.responsive-register>

        @if (count((array) data_get($performanceSimulation, 'mandatory_failures', [])) > 0)
            <div class="logic-simulation-failures" role="alert">
                <strong>Mandatory conditions not met</strong>
                <ul>
                    @foreach ((array) data_get($performanceSimulation, 'mandatory_failures', []) as $failure)
                        <li>{{ $failure }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <dl class="logic-simulation-evidence">
            <div><dt>Rule checksum</dt><dd><code>{{ data_get($performanceSimulation, 'rule_checksum', '-') }}</code></dd></div>
            <div><dt>Input hash</dt><dd><code>{{ data_get($performanceSimulation, 'input_hash', '-') }}</code></dd></div>
            <div><dt>Result hash</dt><dd><code>{{ data_get($performanceSimulation, 'result_hash', '-') }}</code></dd></div>
        </dl>
    </x-ui.card>
@endif

@if ($page->capabilities['manageRosters'] && is_array($rosterSimulation ?? null))
    <x-ui.card id="roster-simulation-result" title="Non-authoritative roster impact simulation" eyebrow="What-if result" meta="{{ data_get($rosterSimulation, 'rotation_name') }} &middot; {{ data_get($rosterSimulation, 'employee_name') }} &middot; {{ data_get($rosterSimulation, 'timezone') }}">
        <div class="logic-simulation-result-guard" role="status">
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            <span><strong>This result cannot affect a roster or attendance.</strong> It mutated {{ data_get($rosterSimulation, 'mutated_records', 0) }} records and is retained only for this response.</span>
        </div>
        <section class="logic-simulation-result-grid" aria-label="Roster impact totals">
            @foreach ([
                ['Preview days', data_get($rosterSimulation, 'counts.days', 0)],
                ['Working shifts', data_get($rosterSimulation, 'counts.shift_days', 0)],
                ['Off days and holidays', data_get($rosterSimulation, 'counts.off_days', 0) + data_get($rosterSimulation, 'counts.holidays', 0)],
                ['Blocking findings', data_get($rosterSimulation, 'counts.blocking_findings', 0)],
            ] as [$label, $value])
                <article><span>{{ $label }}</span><strong>{{ $value }}</strong></article>
            @endforeach
        </section>

        <x-ui.responsive-register label="Roster impact preview">
            <x-slot:desktop>
                <table class="blade-data-table">
                    <caption class="sr-only">Non-mutating roster impact preview by work date</caption>
                    <thead><tr><th scope="col">Work date</th><th scope="col">Cycle day</th><th scope="col">Assignment</th><th scope="col">Local schedule</th><th scope="col">Impact</th></tr></thead>
                    <tbody>
                        @foreach ((array) data_get($rosterSimulation, 'days', []) as $day)
                            <tr>
                                <td><strong>{{ $day['day_label'] }}</strong><small>{{ $day['date'] }}</small></td>
                                <td>{{ $day['cycle_index'] }}</td>
                                <td>{{ $day['shift_name'] ?? str((string) $day['entry_type'])->headline() }} @if ($day['shift_code'])<small>{{ $day['shift_code'] }}</small>@endif</td>
                                <td>{{ $day['starts_at_local'] ?? '-' }} @if ($day['ends_at_local'])<small>to {{ $day['ends_at_local'] }}</small>@endif</td>
                                <td>
                                    @if (count($day['finding_codes']) === 0)
                                        <x-ui.badge tone="success">Clear</x-ui.badge>
                                    @else
                                        @foreach ($day['finding_codes'] as $code)<x-ui.badge :tone="$code === 'authoritative_match' ? 'neutral' : 'danger'">{{ str($code)->replace('_', ' ')->headline() }}</x-ui.badge>@endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-slot:desktop>
            <x-slot:mobile>
                <div class="b360-mobile-register">
                    @foreach ((array) data_get($rosterSimulation, 'days', []) as $day)
                        <article><strong>{{ $day['day_label'] }}</strong><span>{{ $day['shift_name'] ?? str((string) $day['entry_type'])->headline() }}</span><small>{{ $day['starts_at_local'] ?? 'No working hours' }} &middot; {{ count($day['finding_codes']) }} finding(s)</small></article>
                    @endforeach
                </div>
            </x-slot:mobile>
        </x-ui.responsive-register>

        @if (count((array) data_get($rosterSimulation, 'findings', [])) > 0)
            <div class="logic-simulation-failures" role="status">
                <strong>Simulation findings</strong>
                <ul>@foreach ((array) data_get($rosterSimulation, 'findings', []) as $finding)<li><strong>{{ $finding['date'] }}</strong> &mdash; {{ $finding['message'] }}</li>@endforeach</ul>
            </div>
        @endif
        <dl class="logic-simulation-evidence">
            <div><dt>Input hash</dt><dd><code>{{ data_get($rosterSimulation, 'input_hash', '-') }}</code></dd></div>
            <div><dt>Result hash</dt><dd><code>{{ data_get($rosterSimulation, 'result_hash', '-') }}</code></dd></div>
        </dl>
    </x-ui.card>
@endif

@if (is_array($statutorySimulation ?? null))
    @php($simulationResult = (array) data_get($statutorySimulation, 'result', []))
    <x-ui.card id="statutory-simulation-result" title="Non-authoritative statutory simulation" eyebrow="What-if result" meta="{{ data_get($statutorySimulation, 'setting_label') }} · v{{ data_get($statutorySimulation, 'setting_version') }}">
        <div class="logic-simulation-result-guard" role="status">
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            <span><strong>This result cannot affect payroll.</strong> It mutated {{ $simulationResult['mutated_records'] ?? 0 }} records and is retained only for this response.</span>
        </div>
        <section class="logic-simulation-result-grid" aria-label="Simulation totals">
            @foreach ([
                ['Gross', $simulationResult['gross_display'] ?? '0.00'],
                ['Deductions', $simulationResult['deduction_display'] ?? '0.00'],
                ['Employer contribution', $simulationResult['employer_contribution_display'] ?? '0.00'],
                ['Net', $simulationResult['net_display'] ?? '0.00'],
            ] as [$label, $value])
                <article><span>{{ $label }}</span><strong>₹{{ $value }}</strong></article>
            @endforeach
        </section>
        <x-ui.responsive-register label="Statutory simulation lines">
            <x-slot:desktop>
                <table class="blade-data-table">
                    <caption class="sr-only">Calculated statutory simulation lines</caption>
                    <thead><tr><th scope="col">Line</th><th scope="col">Jurisdiction</th><th scope="col">Method</th><th scope="col">Basis</th><th scope="col">Amount</th></tr></thead>
                    <tbody>
                        @forelse ((array) ($simulationResult['lines'] ?? []) as $line)
                            <tr><td><strong>{{ $line['component_name'] }}</strong><small>{{ $line['component_code'] }} · {{ str($line['line_type'])->headline() }}</small></td><td>{{ strtoupper($line['jurisdiction_code']) }}</td><td>{{ str($line['method'])->headline() }}</td><td>₹{{ $line['basis_display'] }}</td><td>₹{{ $line['amount_display'] }}</td></tr>
                        @empty
                            <tr><td colspan="5">No governed statutory line applied to the supplied state and population context.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-slot:desktop>
            <x-slot:mobile>
                <div class="b360-mobile-register">
                    @forelse ((array) ($simulationResult['lines'] ?? []) as $line)
                        <article><strong>{{ $line['component_name'] }}</strong><span>{{ $line['component_code'] }} · {{ strtoupper($line['jurisdiction_code']) }}</span><small>Basis ₹{{ $line['basis_display'] }} · Amount ₹{{ $line['amount_display'] }}</small></article>
                    @empty
                        <x-ui.empty-state title="No applicable calculation lines" description="The pack did not apply to the supplied state and population context." icon="fa-circle-info" />
                    @endforelse
                </div>
            </x-slot:mobile>
        </x-ui.responsive-register>
        <dl class="logic-simulation-evidence">
            <div><dt>State</dt><dd>{{ $simulationResult['statutory_state'] ?? '—' }}</dd></div>
            <div><dt>Input hash</dt><dd><code>{{ $simulationResult['input_hash'] ?? '—' }}</code></dd></div>
            <div><dt>Result hash</dt><dd><code>{{ $simulationResult['result_hash'] ?? '—' }}</code></dd></div>
        </dl>
    </x-ui.card>
@endif

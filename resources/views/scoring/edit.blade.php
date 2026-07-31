@extends('layouts.builder360-classic')

@section('title', 'Edit '.$rule->name.' | Builder360')

@section('content')
    <x-ui.page-header eyebrow="Scoring Logic" title="Edit scoring rule" description="Update controlled criteria, score bands and decision thresholds. Saving returns this version to Draft for validation and approval.">
        <x-slot:actions>
            <x-ui.action :href="route('scoring.index', ['view' => 'rule-history'])">Back to rule history</x-ui.action>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('scoring.rules.update', $rule->id) }}" class="b360-scoring-editor">
        @csrf
        @method('PATCH')

        <x-ui.card title="Rule identity" eyebrow="{{ str($rule->ruleKey)->headline() }}" meta="Version {{ $rule->version }} · {{ $rule->status }}">
            <div class="blade-form-grid b360-editor-grid">
                <x-forms.field name="name" label="Rule name" required>
                    <x-forms.input name="name" :value="old('name', $rule->name)" maxlength="140" required />
                </x-forms.field>
                <x-forms.field name="effective_at" label="Planned effective date">
                    <x-forms.input name="effective_at" type="datetime-local" :value="old('effective_at', $rule->effectiveAt)" />
                </x-forms.field>
                <x-forms.field name="change_reason" label="Change reason" hint="Required for approval and rule history." required>
                    <x-forms.textarea name="change_reason" rows="3" maxlength="2000" required>{{ old('change_reason', $rule->changeReason) }}</x-forms.textarea>
                </x-forms.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Scoring criteria" eyebrow="Weights and points" meta="Weights must total 100%">
            <div class="b360-editor-stack">
                @foreach ($rule->criteria as $criterionIndex => $criterion)
                    <fieldset class="b360-editor-section">
                        <legend>Criterion {{ $criterionIndex + 1 }} · {{ $criterion->label }}</legend>
                        <div class="b360-editor-grid b360-editor-grid-four">
                            <x-forms.field name="criteria_{{ $criterionIndex }}_key" label="Key" required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][key]" :value="old('criteria.'.$criterionIndex.'.key', $criterion->key)" required />
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_label" label="Business label" required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][label]" :value="old('criteria.'.$criterionIndex.'.label', $criterion->label)" required />
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_weight" label="Weight %" required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][weight]" type="number" step="0.01" min="0" max="100" :value="old('criteria.'.$criterionIndex.'.weight', $criterion->weight)" required />
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_points" label="Maximum points" required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][max_points]" type="number" step="0.01" min="0.01" max="100" :value="old('criteria.'.$criterionIndex.'.max_points', $criterion->maxPoints)" required />
                            </x-forms.field>
                        </div>

                        <div class="b360-editor-grid b360-editor-grid-four" aria-label="Criterion input governance">
                            <x-forms.field name="criteria_{{ $criterionIndex }}_source" label="Structured input source" hint="Safe key read from the authorized source record." required>
                                @if ($rule->ruleKey === 'employee_performance')
                                    <x-forms.select name="criteria[{{ $criterionIndex }}][source]" required>
                                        @foreach ($rule->performanceSourceOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('criteria.'.$criterionIndex.'.source', $criterion->source) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-forms.select>
                                @else
                                    <x-forms.input name="criteria[{{ $criterionIndex }}][source]" :value="old('criteria.'.$criterionIndex.'.source', $criterion->source)" required />
                                @endif
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_normalization" label="Normalization" required>
                                <x-forms.select name="criteria[{{ $criterionIndex }}][normalization]" required>
                                    @foreach (['rating_scale' => 'Rating scale to 0-100', 'percentage' => 'Percentage (0-100)', 'points' => 'Points to 0-100'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('criteria.'.$criterionIndex.'.normalization', $criterion->normalization) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-forms.select>
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_input_scale_min" label="Input scale minimum" hint="Raw source value representing the zero floor." required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][input_scale][min]" type="number" step="0.01" min="0" :value="old('criteria.'.$criterionIndex.'.input_scale.min', $criterion->inputScaleMin)" required />
                            </x-forms.field>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_input_scale_max" label="Input scale maximum" hint="Raw source value normalized to 100%." required>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][input_scale][max]" type="number" step="0.01" min="0.01" :value="old('criteria.'.$criterionIndex.'.input_scale.max', $criterion->inputScaleMax)" required />
                            </x-forms.field>
                            <div class="b360-field">
                                <span>Required evidence</span>
                                <input type="hidden" name="criteria[{{ $criterionIndex }}][required]" value="0">
                                <label class="b360-check-option">
                                    <input type="checkbox" name="criteria[{{ $criterionIndex }}][required]" value="1" @checked((bool) old('criteria.'.$criterionIndex.'.required', $criterion->required))>
                                    Block finalization when missing
                                </label>
                            </div>
                            <x-forms.field name="criteria_{{ $criterionIndex }}_missing_data" label="Missing optional data" required>
                                <x-forms.select name="criteria[{{ $criterionIndex }}][missing_data_behavior]" required>
                                    @foreach (['block' => 'Block calculation', 'zero' => 'Apply zero contribution', 'reweight' => 'Reweight available criteria'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('criteria.'.$criterionIndex.'.missing_data_behavior', $criterion->missingDataBehavior) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-forms.select>
                            </x-forms.field>
                        </div>

                        <div class="b360-editor-subsection">
                            <h3>Decision conditions</h3>
                            @forelse ($criterion->conditions as $conditionIndex => $condition)
                                <div class="b360-condition-row">
                                    <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][key]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$conditionIndex.'.key', $condition->key)" aria-label="Condition key" required />
                                    <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][label]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$conditionIndex.'.label', $condition->label)" aria-label="Condition label" required />
                                    <x-forms.select name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][operator]" aria-label="Condition operator" required>
                                        @foreach (['equals' => 'Equals', 'not_equals' => 'Does not equal', 'greater_or_equal' => 'At least', 'less_or_equal' => 'At most', 'between' => 'Between', 'in' => 'Any listed value', 'boolean' => 'Yes or no'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('criteria.'.$criterionIndex.'.conditions.'.$conditionIndex.'.operator', $condition->operator) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-forms.select>
                                    <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][value]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$conditionIndex.'.value', $condition->value)" aria-label="Condition value" required />
                                    <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][points]" type="number" step="0.01" min="-100" max="100" :value="old('criteria.'.$criterionIndex.'.conditions.'.$conditionIndex.'.points', $condition->points)" aria-label="Condition points" required />
                                    <label class="b360-check-option"><input type="checkbox" name="criteria[{{ $criterionIndex }}][conditions][{{ $conditionIndex }}][remove]" value="1"> Remove</label>
                                </div>
                            @empty
                                <p class="b360-editor-empty">No decision conditions are configured for this criterion.</p>
                            @endforelse
                            @php($newConditionIndex = count($criterion->conditions))
                            <div class="b360-condition-row" aria-label="New optional condition">
                                <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $newConditionIndex }}][key]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$newConditionIndex.'.key')" aria-label="New condition key" placeholder="new_condition" />
                                <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $newConditionIndex }}][label]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$newConditionIndex.'.label')" aria-label="New condition label" placeholder="Condition label" />
                                <x-forms.select name="criteria[{{ $criterionIndex }}][conditions][{{ $newConditionIndex }}][operator]" aria-label="New condition operator">
                                    @foreach (['equals' => 'Equals', 'not_equals' => 'Does not equal', 'greater_or_equal' => 'At least', 'less_or_equal' => 'At most', 'between' => 'Between', 'in' => 'Any listed value', 'boolean' => 'Yes or no'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('criteria.'.$criterionIndex.'.conditions.'.$newConditionIndex.'.operator', 'equals') === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-forms.select>
                                <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $newConditionIndex }}][value]" :value="old('criteria.'.$criterionIndex.'.conditions.'.$newConditionIndex.'.value')" aria-label="New condition value" placeholder="Expected value" />
                                <x-forms.input name="criteria[{{ $criterionIndex }}][conditions][{{ $newConditionIndex }}][points]" type="number" step="0.01" min="-100" max="100" :value="old('criteria.'.$criterionIndex.'.conditions.'.$newConditionIndex.'.points')" aria-label="New condition points" placeholder="Points" />
                                <span class="b360-editor-hint">Optional</span>
                            </div>
                        </div>
                        <label class="b360-check-option b360-remove-option"><input type="checkbox" name="criteria[{{ $criterionIndex }}][remove]" value="1"> Remove this criterion</label>
                    </fieldset>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Score bands" eyebrow="Outcomes" meta="Include a 0-point floor band">
            <div class="b360-band-register">
                @foreach ($rule->bands as $bandIndex => $band)
                    <div class="b360-band-row">
                        <x-forms.input name="bands[{{ $bandIndex }}][key]" :value="old('bands.'.$bandIndex.'.key', $band->key)" aria-label="Band key" required />
                        <x-forms.input name="bands[{{ $bandIndex }}][label]" :value="old('bands.'.$bandIndex.'.label', $band->label)" aria-label="Band label" required />
                        <x-forms.input name="bands[{{ $bandIndex }}][min_score]" type="number" min="0" max="100" :value="old('bands.'.$bandIndex.'.min_score', $band->minScore)" aria-label="Minimum score" required />
                        <x-forms.input name="bands[{{ $bandIndex }}][outcome]" :value="old('bands.'.$bandIndex.'.outcome', $band->outcome)" aria-label="Business outcome" required />
                        <label class="b360-check-option"><input type="checkbox" name="bands[{{ $bandIndex }}][remove]" value="1"> Remove</label>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <div class="b360-dashboard-grid">
            <x-ui.card title="Rating and thresholds" eyebrow="Decision limits">
                <div class="blade-form-grid b360-editor-grid">
                    @foreach ([
                        ['rating_min', 'Rating minimum', $rule->ratingMin], ['rating_max', 'Rating maximum', $rule->ratingMax],
                        ['passing_score', 'Passing score', $rule->passingScore], ['pip_score', 'Improvement threshold', $rule->pipScore],
                    ] as [$name, $label, $value])
                        <x-forms.field :name="$name" :label="$label" required>
                            <x-forms.input :name="$name" type="number" step="0.01" min="0" max="100" :value="old($name, $value)" required />
                        </x-forms.field>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Calculation controls" eyebrow="Precision and evidence">
                <div class="blade-form-grid b360-editor-grid">
                    <x-forms.field name="rounding_method" label="Rounding method" required>
                        <x-forms.select name="rounding_method" required>
                            @foreach (['half_up' => 'Round half up', 'half_even' => 'Round half even', 'floor' => 'Round down', 'ceil' => 'Round up'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('rounding_method', $rule->roundingMethod) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-forms.select>
                    </x-forms.field>
                    <x-forms.field name="rounding_precision" label="Decimal places" required>
                        <x-forms.input name="rounding_precision" type="number" min="0" max="4" :value="old('rounding_precision', $rule->roundingPrecision)" required />
                    </x-forms.field>
                    <x-forms.field name="minimum_sample_size" label="Minimum sample size" required>
                        <x-forms.input name="minimum_sample_size" type="number" min="1" max="10000" :value="old('minimum_sample_size', $rule->minimumSampleSize)" required />
                    </x-forms.field>
                </div>
                <div class="b360-check-list">
                    <label class="b360-check-option"><input type="checkbox" name="override_allowed" value="1" @checked(old('override_allowed', $rule->overrideAllowed))> Allow authorized score overrides</label>
                    <label class="b360-check-option"><input type="checkbox" name="override_reason_required" value="1" @checked(old('override_reason_required', $rule->overrideReasonRequired))> Require an override reason</label>
                </div>
            </x-ui.card>
        </div>

        <div class="b360-sticky-form-actions">
            <span>Saving changes returns this version to Draft.</span>
            <x-ui.action :href="route('scoring.index', ['view' => 'rule-history'])">Cancel</x-ui.action>
            <x-ui.action type="submit" variant="primary">Save rule draft</x-ui.action>
        </div>
    </form>
@endsection

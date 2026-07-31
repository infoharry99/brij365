@if ($page->capabilities['manageStatutory'])
<details class="logic-pack-editor" @if($errors->any() && old('return_to') === 'logic_center') open @endif>
    <summary>
        <span><i class="fa-solid fa-plus" aria-hidden="true"></i><strong>Create governed statutory pack draft</strong></span>
        <small>Official-source evidence, deterministic minor-unit formulas and maker-checker review are mandatory.</small>
    </summary>

    @if($errors->any() && old('return_to') === 'logic_center')
        <div class="logic-guard-notice is-danger" role="alert" tabindex="-1">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span><strong>The statutory draft was not created.</strong> Correct the highlighted definition fields; no payroll or setting record was changed.</span>
        </div>
    @endif

    <form method="POST" action="{{ route('hr.compliance-rule-settings.store') }}" class="logic-pack-form" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Create governed draft" data-busy-label="Creating governed draft...">
        @csrf
        <input type="hidden" name="return_to" value="logic_center">
        <input type="hidden" name="value[governed_statutory_pack_version]" value="1">
        <input type="hidden" name="value[statutory_validation_required]" value="1">
        <input type="hidden" name="value[source_evidence][0][source_type]" value="official_government">

        <fieldset class="logic-pack-fieldset">
            <legend>Version identity and separation of duties</legend>
            <div class="blade-form-grid">
                <x-forms.field name="setting_key" label="Statutory pack type" required>
                    <x-forms.select name="setting_key" required>
                        <option value="">Select governed pack type</option>
                        @foreach($page->statutoryPackTypes as $key => $label)<option value="{{ $key }}" @selected(old('setting_key') === $key)>{{ $label }}</option>@endforeach
                    </x-forms.select>
                </x-forms.field>
                <x-forms.field name="label" label="Version label" required><x-forms.input name="label" :value="old('label')" maxlength="255" required /></x-forms.field>
                <x-forms.field name="effective_from" label="Pack effective from" required><x-forms.input name="effective_from" type="date" :value="old('effective_from')" required /></x-forms.field>
                <x-forms.field name="value[approval_chain][0]" label="Independent verifier role" required><x-forms.input name="value[approval_chain][0]" :value="old('value.approval_chain.0')" placeholder="e.g. compliance_verifier" required /></x-forms.field>
                <x-forms.field name="value[approval_chain][1]" label="Independent approver role" required><x-forms.input name="value[approval_chain][1]" :value="old('value.approval_chain.1')" placeholder="e.g. statutory_approver" required /></x-forms.field>
                <x-forms.field name="description" label="Change reason and scope"><x-forms.textarea name="description" rows="3" maxlength="5000">{{ old('description') }}</x-forms.textarea></x-forms.field>
            </div>
        </fieldset>

        <fieldset class="logic-pack-fieldset">
            <legend>Official Government source evidence</legend>
            <p class="logic-pack-help">Use the SHA-256 checksum of the exact official document or captured evidence reviewed. Only HTTPS Government domains or explicitly approved statutory-authority hosts are accepted.</p>
            <div class="blade-form-grid">
                <x-forms.field name="value[source_evidence][0][authority]" label="Issuing authority" required><x-forms.input name="value[source_evidence][0][authority]" :value="old('value.source_evidence.0.authority')" required /></x-forms.field>
                <x-forms.field name="value[source_evidence][0][title]" label="Official document title" required><x-forms.input name="value[source_evidence][0][title]" :value="old('value.source_evidence.0.title')" required /></x-forms.field>
                <x-forms.field name="value[source_evidence][0][document_reference]" label="Notification / circular reference" required><x-forms.input name="value[source_evidence][0][document_reference]" :value="old('value.source_evidence.0.document_reference')" required /></x-forms.field>
                <x-forms.field name="value[source_evidence][0][url]" label="Official source URL" required><x-forms.input name="value[source_evidence][0][url]" type="url" :value="old('value.source_evidence.0.url')" placeholder="https://...gov.in/..." required /></x-forms.field>
                <x-forms.field name="value[source_evidence][0][published_or_accessed_on]" label="Published / accessed on" required><x-forms.input name="value[source_evidence][0][published_or_accessed_on]" type="date" :value="old('value.source_evidence.0.published_or_accessed_on')" required /></x-forms.field>
                <x-forms.field name="value[source_evidence][0][source_checksum]" label="Official evidence SHA-256" hint="Exactly 64 hexadecimal characters." required><x-forms.input name="value[source_evidence][0][source_checksum]" :value="old('value.source_evidence.0.source_checksum')" minlength="64" maxlength="64" pattern="[A-Fa-f0-9]{64}" required /></x-forms.field>
            </div>
        </fieldset>

        <fieldset class="logic-pack-fieldset">
            <legend>Jurisdiction and applicability</legend>
            <div class="blade-form-grid">
                <x-forms.field name="value[jurisdictions][0][type]" label="Jurisdiction type" required><x-forms.select name="value[jurisdictions][0][type]" required><option value="central" @selected(old('value.jurisdictions.0.type', 'central') === 'central')>Central</option><option value="state" @selected(old('value.jurisdictions.0.type') === 'state')>Employee statutory state</option></x-forms.select></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][code]" label="Jurisdiction code" hint="Use IN for central or the approved state code." required><x-forms.input name="value[jurisdictions][0][code]" :value="old('value.jurisdictions.0.code', 'IN')" minlength="2" maxlength="8" required /></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][state_resolution]" label="State resolution" required><x-forms.select name="value[jurisdictions][0][state_resolution]" required><option value="allow_no_match" @selected(old('value.jurisdictions.0.state_resolution', 'allow_no_match') === 'allow_no_match')>Allow central fallback</option><option value="required_match" @selected(old('value.jurisdictions.0.state_resolution') === 'required_match')>Require exact employee-state match</option></x-forms.select></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][effective_from]" label="Jurisdiction effective from" required><x-forms.input name="value[jurisdictions][0][effective_from]" type="date" :value="old('value.jurisdictions.0.effective_from')" required /></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][effective_to]" label="Jurisdiction effective to"><x-forms.input name="value[jurisdictions][0][effective_to]" type="date" :value="old('value.jurisdictions.0.effective_to')" /></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][applicability][employment_types]" label="Employment types" hint="Optional comma-separated governed values."><x-forms.input name="value[jurisdictions][0][applicability][employment_types]" :value="old('value.jurisdictions.0.applicability.employment_types')" /></x-forms.field>
                <x-forms.field name="value[jurisdictions][0][applicability][departments]" label="Departments" hint="Optional comma-separated governed values."><x-forms.input name="value[jurisdictions][0][applicability][departments]" :value="old('value.jurisdictions.0.applicability.departments')" /></x-forms.field>
            </div>
        </fieldset>

        <fieldset class="logic-pack-fieldset">
            <legend>Deterministic calculation lines</legend>
            <p class="logic-pack-help">Amounts use integer minor currency units. Rates use integer parts per million (100,000 = 10%). Leave unused optional rows blank.</p>
            @for($line = 0; $line < 4; $line++)
                <section class="logic-calculation-line" aria-labelledby="logic-line-{{ $line }}-title">
                    <h3 id="logic-line-{{ $line }}-title">Calculation line {{ $line + 1 }}@if($line === 0) <span aria-hidden="true">*</span>@endif</h3>
                    <div class="blade-form-grid">
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][code]" label="Stable code"><x-forms.input name="value[jurisdictions][0][lines][{{ $line }}][code]" :value="old('value.jurisdictions.0.lines.'.$line.'.code')" :required="$line === 0" /></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][name]" label="Line name"><x-forms.input name="value[jurisdictions][0][lines][{{ $line }}][name]" :value="old('value.jurisdictions.0.lines.'.$line.'.name')" :required="$line === 0" /></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][line_type]" label="Line type"><x-forms.select name="value[jurisdictions][0][lines][{{ $line }}][line_type]" :required="$line === 0"><option value="">Select</option>@foreach(['earning'=>'Earning','deduction'=>'Employee deduction','employer_contribution'=>'Employer contribution','tax_adjustment'=>'Tax adjustment'] as $value => $label)<option value="{{ $value }}" @selected(old('value.jurisdictions.0.lines.'.$line.'.line_type') === $value)>{{ $label }}</option>@endforeach</x-forms.select></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][method]" label="Method"><x-forms.select name="value[jurisdictions][0][lines][{{ $line }}][method]" :required="$line === 0"><option value="">Select</option><option value="rate_ppm" @selected(old('value.jurisdictions.0.lines.'.$line.'.method') === 'rate_ppm')>Rate (parts per million)</option><option value="fixed_minor" @selected(old('value.jurisdictions.0.lines.'.$line.'.method') === 'fixed_minor')>Fixed amount (minor units)</option></x-forms.select></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][basis_codes]" label="Basis component codes" hint="Comma-separated; required for rate lines."><x-forms.input name="value[jurisdictions][0][lines][{{ $line }}][basis_codes]" :value="old('value.jurisdictions.0.lines.'.$line.'.basis_codes')" placeholder="BASIC, DA" /></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][rate_ppm]" label="Rate (ppm)"><x-forms.input name="value[jurisdictions][0][lines][{{ $line }}][rate_ppm]" type="number" min="0" max="1000000" step="1" :value="old('value.jurisdictions.0.lines.'.$line.'.rate_ppm')" /></x-forms.field>
                        <x-forms.field name="value[jurisdictions][0][lines][{{ $line }}][fixed_minor]" label="Fixed minor-unit amount"><x-forms.input name="value[jurisdictions][0][lines][{{ $line }}][fixed_minor]" type="number" min="0" step="1" :value="old('value.jurisdictions.0.lines.'.$line.'.fixed_minor')" /></x-forms.field>
                    </div>
                </section>
            @endfor
        </fieldset>

        <fieldset class="logic-pack-fieldset">
            <legend>Attendance proration authority</legend>
            <div class="blade-form-grid">
                <x-forms.field name="value[attendance_proration][enabled]" label="Prorate configured components" required><x-forms.select name="value[attendance_proration][enabled]" required><option value="0" @selected(old('value.attendance_proration.enabled', '0') === '0')>No</option><option value="1" @selected(old('value.attendance_proration.enabled') === '1')>Yes</option></x-forms.select></x-forms.field>
                <x-forms.field name="value[attendance_proration][component_codes]" label="Prorated component codes" hint="Required only when proration is enabled."><x-forms.input name="value[attendance_proration][component_codes]" :value="old('value.attendance_proration.component_codes')" placeholder="BASIC, HRA" /></x-forms.field>
            </div>
        </fieldset>

        <div class="logic-guard-notice" role="note">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span><strong>Draft only.</strong> Creating this version does not make it authoritative. A different verifier must attest to the source checksum and a third authorized actor must approve it.</span>
        </div>
        <div class="blade-form-actions"><x-ui.action type="submit" variant="primary" x-bind:disabled="busy"><span x-text="submitLabel">Create governed draft</span></x-ui.action></div>
    </form>
</details>
@endif

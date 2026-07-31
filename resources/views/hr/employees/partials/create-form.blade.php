<section
    id="create-employee"
    class="people-modal {{ $errors->any() ? 'is-open' : '' }}"
    x-bind:class="createModalClasses"
    x-bind:aria-hidden="createAriaHidden"
    aria-hidden="{{ $errors->any() ? 'false' : 'true' }}"
    aria-labelledby="create-employee-title"
>
    <a class="people-modal-backdrop" href="{{ route('hr.employees.index') }}" x-on:click.prevent="closeCreateEmployee" aria-label="Close create employee dialog"></a>

    <form
        method="POST"
        action="{{ route('hr.employees.store') }}"
        class="people-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-employee-title"
        aria-describedby="create-employee-description"
        x-ref="createDialog"
        x-on:submit="submitEmployeeForm"
        x-bind:aria-busy="submitting"
        x-on:keydown="trapCreateFocus"
    >
        @csrf
        <header class="people-modal-head">
            <div class="people-modal-title">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <div>
                    <h2 id="create-employee-title">Create employee record</h2>
                    <p id="create-employee-description">Create an employee identity and initial work placement.</p>
                </div>
            </div>
            <a href="{{ route('hr.employees.index') }}" class="people-icon-button" x-on:click.prevent="closeCreateEmployee" aria-label="Close create employee dialog">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </a>
        </header>

        <div class="people-modal-body">
            <x-forms.company-context :companies="$companies" required />

            <div class="people-form-grid">
                <label class="people-field" for="employee-code">
                    <span>Employee code *</span>
                    <input id="employee-code" class="people-control" name="employee_code" value="{{ old('employee_code') }}" maxlength="32" placeholder="EMP-0045" required autocomplete="off" @if($errors->has('employee_code')) aria-invalid="true" aria-describedby="employee-code-error" @endif>
                    @error('employee_code')<span class="people-field-error" id="employee-code-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-name">
                    <span>Employee name *</span>
                    <input id="employee-name" class="people-control" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name" @if($errors->has('name')) aria-invalid="true" aria-describedby="employee-name-error" @endif>
                    @error('name')<span class="people-field-error" id="employee-name-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-designation">
                    <span>Designation *</span>
                    <input id="employee-designation" class="people-control" name="designation" value="{{ old('designation') }}" list="employee-designations" maxlength="120" required @if($errors->has('designation')) aria-invalid="true" aria-describedby="employee-designation-error" @endif>
                    <datalist id="employee-designations">@foreach ($designations as $designation)<option value="{{ $designation }}">@endforeach</datalist>
                    @error('designation')<span class="people-field-error" id="employee-designation-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-department">
                    <span>Department *</span>
                    <input id="employee-department" class="people-control" name="department" value="{{ old('department') }}" list="employee-departments" maxlength="120" required @if($errors->has('department')) aria-invalid="true" aria-describedby="employee-department-error" @endif>
                    <datalist id="employee-departments">@foreach ($departments as $department)<option value="{{ $department }}">@endforeach</datalist>
                    @error('department')<span class="people-field-error" id="employee-department-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-branch">
                    <span>Branch / site</span>
                    <select id="employee-branch" class="people-control" name="branch_id" @if($errors->has('branch_id')) aria-invalid="true" aria-describedby="employee-branch-error" @endif>
                        <option value="">No branch assignment</option>
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach
                    </select>
                    @error('branch_id')<span class="people-field-error" id="employee-branch-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-project">
                    <span>Primary project</span>
                    <select id="employee-project" class="people-control" name="project_id" @if($errors->has('project_id')) aria-invalid="true" aria-describedby="employee-project-error" @endif>
                        <option value="">All-project employee</option>
                        @foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} - {{ $project->name }}</option>@endforeach
                    </select>
                    @error('project_id')<span class="people-field-error" id="employee-project-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-manager">
                    <span>Reporting manager</span>
                    <select id="employee-manager" class="people-control" name="manager_employee_id" @if($errors->has('manager_employee_id')) aria-invalid="true" aria-describedby="employee-manager-error" @endif>
                        <option value="">No reporting manager</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}" @selected((string) old('manager_employee_id') === (string) $manager->id)>{{ $manager->employee_code }} - {{ $manager->name }}{{ $manager->designation ? ' / '.$manager->designation : '' }}</option>@endforeach
                    </select>
                    @error('manager_employee_id')<span class="people-field-error" id="employee-manager-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-user">
                    <span>Application user</span>
                    <select id="employee-user" class="people-control" name="user_id" @if($errors->has('user_id')) aria-invalid="true" aria-describedby="employee-user-error" @endif>
                        <option value="">No login linked</option>
                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} - {{ $user->email }}</option>@endforeach
                    </select>
                    @error('user_id')<span class="people-field-error" id="employee-user-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-type">
                    <span>Employment type *</span>
                    <select id="employee-type" class="people-control" name="employment_type" required @if($errors->has('employment_type')) aria-invalid="true" aria-describedby="employee-type-error" @endif>
                        @foreach ($employmentTypes as $value => $label)<option value="{{ $value }}" @selected(old('employment_type', 'full_time') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('employment_type')<span class="people-field-error" id="employee-type-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-status">
                    <span>Status *</span>
                    <select id="employee-status" class="people-control" name="status" required @if($errors->has('status')) aria-invalid="true" aria-describedby="employee-status-error" @endif>
                        @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('status')<span class="people-field-error" id="employee-status-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-grade">
                    <span>Grade</span>
                    <input id="employee-grade" class="people-control" name="grade" value="{{ old('grade') }}" maxlength="16" @if($errors->has('grade')) aria-invalid="true" aria-describedby="employee-grade-error" @endif>
                    @error('grade')<span class="people-field-error" id="employee-grade-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-joined">
                    <span>Joining date</span>
                    <input id="employee-joined" type="date" class="people-control" name="joined_on" value="{{ old('joined_on') }}" max="{{ now()->toDateString() }}" @if($errors->has('joined_on')) aria-invalid="true" aria-describedby="employee-joined-error" @endif>
                    @error('joined_on')<span class="people-field-error" id="employee-joined-error">{{ $message }}</span>@enderror
                </label>

                <label class="people-field" for="employee-state">
                    <span>Statutory state code</span>
                    <input id="employee-state" class="people-control" name="statutory_state" value="{{ old('statutory_state') }}" maxlength="8" placeholder="MH" @if($errors->has('statutory_state')) aria-invalid="true" aria-describedby="employee-state-error" @endif>
                    @error('statutory_state')<span class="people-field-error" id="employee-state-error">{{ $message }}</span>@enderror
                </label>

                @if ($abilities['canViewCompensation'])
                    <label class="people-field" for="employee-ctc">
                        <span>Monthly CTC</span>
                        <input id="employee-ctc" type="number" class="people-control" name="monthly_ctc" value="{{ old('monthly_ctc') }}" min="0" step="0.01" @if($errors->has('monthly_ctc')) aria-invalid="true" aria-describedby="employee-ctc-error" @endif>
                        <small>Visible only to authorized HR and payroll roles.</small>
                        @error('monthly_ctc')<span class="people-field-error" id="employee-ctc-error">{{ $message }}</span>@enderror
                    </label>
                @endif
            </div>
        </div>

        <footer class="people-modal-foot">
            <p>Required fields are marked with an asterisk.</p>
            <div class="people-modal-actions">
                <a href="{{ route('hr.employees.index') }}" class="people-button" x-on:click.prevent="closeCreateEmployee">Cancel</a>
                <button type="submit" class="people-button is-primary" x-bind:disabled="submitting">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    <span x-text="submitLabel">Create employee</span>
                </button>
            </div>
        </footer>
    </form>
</section>

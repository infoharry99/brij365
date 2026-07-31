@if ($asset->canAssign)
    <details>
        <summary class="people-ops-action-link" aria-label="Assign asset {{ $asset->assetCode }}">Assign</summary>
        <form method="POST" action="{{ route('hr.assets.assign', $asset->id) }}" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Assign asset" data-busy-label="Assigning…">
            @csrf
            @method('PATCH')
            <label class="people-field">
                <span>Employee</span>
                <select class="people-control" name="employee_id" required>
                    <option value="">Select employee</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">
                <span>Assigned on</span>
                <input class="people-control" type="date" name="assigned_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </label>
            <label class="people-field">
                <span>Assignment note</span>
                <textarea class="people-control" name="note" maxlength="1000" rows="2" placeholder="Optional governed assignment note"></textarea>
            </label>
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Assign asset</span></button>
        </form>
    </details>
@endif

@if ($asset->canRecover)
    <details>
        <summary class="people-ops-action-link" aria-label="Recover asset {{ $asset->assetCode }} from {{ $asset->employeeName }}">Recover</summary>
        <form method="POST" action="{{ route('hr.assets.recover', $asset->id) }}" x-data="serverFormState" x-on:submit="beginSubmit" x-bind:aria-busy="busyAria" data-idle-label="Record recovery" data-busy-label="Recording…">
            @csrf
            @method('PATCH')
            <label class="people-field">
                <span>Condition</span>
                <select class="people-control" name="condition" required>
                    @foreach ($assetConditions as $condition)
                        <option value="{{ $condition }}" @selected($asset->condition === $condition)>{{ ucfirst($condition) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="people-field">
                <span>Outcome</span>
                <select class="people-control" name="status">
                    <option value="recovered">Recovered</option>
                    <option value="retired">Retired</option>
                    <option value="lost">Lost</option>
                </select>
            </label>
            <label class="people-field">
                <span>Recovered on</span>
                <input class="people-control" type="date" name="recovered_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </label>
            <label class="people-field">
                <span>Recovery note</span>
                <textarea class="people-control" name="note" maxlength="1000" rows="2" placeholder="Optional governed recovery note"></textarea>
            </label>
            <button class="people-button is-primary" type="submit" x-bind:disabled="busy"><span x-text="submitLabel">Record recovery</span></button>
        </form>
    </details>
@endif

@if (! $asset->canAssign && ! $asset->canRecover)
    <span class="people-subtext">No action available</span>
@endif

<x-ui.responsive-register class="people-register-shell" label="Employee directory results">
    <x-slot:desktop>
        <table class="people-register">
            <caption>Employees matching the current directory filters</caption>
            <thead>
                <tr>
                    <th scope="col">Employee</th>
                    <th scope="col">Department</th>
                    <th scope="col">Company / Site</th>
                    <th scope="col">Grade</th>
                    <th scope="col">Attendance</th>
                    <th scope="col">Net Salary</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    @php($row = $directoryRows->get($employee->id))
                    <tr>
                        <td>
                            <div class="people-identity">
                                <x-ui.user-avatar :user="$employee->user" :label="$row->name" class="people-avatar" />
                                <div>
                                    <a href="{{ route('hr.employees.show', $employee) }}" aria-label="Open employee profile for {{ $row->name }}">{{ $row->name }}</a>
                                    <span class="people-subtext">{{ $row->employeeCode }} - {{ $row->designation }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $row->department }}<span class="people-subtext">{{ $row->manager }}</span></td>
                        <td>{{ $row->company }}<span class="people-subtext">{{ $row->branch }} / {{ $row->project }}</span></td>
                        <td>{{ $row->grade ?? 'Not recorded' }}</td>
                        <td><div class="people-attendance"><small>{{ $row->attendanceLabel }}</small></div></td>
                        <td>
                            @if (! $abilities['canViewCompensation'])
                                <span class="people-compensation is-restricted">Restricted</span>
                            @elseif ($row->latestApprovedNetSalary !== null)
                                <span class="people-compensation">INR {{ number_format($row->latestApprovedNetSalary, 2) }}</span>
                            @else
                                <span class="people-compensation is-restricted">No approved payroll</span>
                            @endif
                        </td>
                        <td><span class="people-status is-{{ $row->statusTone }}">{{ $row->statusLabel }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:desktop>

    <x-slot:mobile>
        <div class="people-mobile-cards">
            @foreach ($employees as $employee)
                @php($row = $directoryRows->get($employee->id))
                <article class="people-employee-card">
                    <div class="people-card-head">
                        <div class="people-identity">
                            <x-ui.user-avatar :user="$employee->user" :label="$row->name" class="people-avatar" />
                            <div><strong>{{ $row->name }}</strong><span class="people-subtext">{{ $row->employeeCode }} - {{ $row->designation }}</span></div>
                        </div>
                        <span class="people-status is-{{ $row->statusTone }}">{{ $row->statusLabel }}</span>
                    </div>
                    <dl class="people-card-facts">
                        <div><dt>Department</dt><dd>{{ $row->department }}</dd></div>
                        <div><dt>Company / site</dt><dd>{{ $row->company }}<span class="people-subtext">{{ $row->branch }}</span></dd></div>
                        <div><dt>Grade</dt><dd>{{ $row->grade ?? 'Not recorded' }}</dd></div>
                        <div><dt>Attendance</dt><dd>{{ $row->attendanceLabel }}</dd></div>
                        <div><dt>Manager</dt><dd>{{ $row->manager }}</dd></div>
                        <div><dt>Net salary</dt><dd>@if(! $abilities['canViewCompensation']) Restricted @elseif($row->latestApprovedNetSalary !== null) INR {{ number_format($row->latestApprovedNetSalary, 2) }} @else No approved payroll @endif</dd></div>
                    </dl>
                    <div class="people-card-action"><a class="people-button" href="{{ route('hr.employees.show', $employee) }}" aria-label="Open employee profile for {{ $row->name }}">View Employee 360</a></div>
                </article>
            @endforeach
        </div>
    </x-slot:mobile>
</x-ui.responsive-register>

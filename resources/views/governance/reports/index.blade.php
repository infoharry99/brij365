@extends('layouts.builder360-classic')

@section('title', 'Reports & Analytics | Builder360 ERP-CRM')

@section('content')
    @php
        $columns = $page->rows === [] ? [] : array_keys($page->rows[0]);
        $exportQuery = array_filter($page->filters, static fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="blade-workspace" aria-labelledby="report-register-title">
        <x-ui.page-header
            title="Reports & Analytics"
            heading-id="report-register-title"
            eyebrow="Management Information"
            description="Review authorized business registers, apply project and period filters, and export the current result."
        >
            <x-slot:actions>
                <x-ui.action :href="route('builder360.dashboard')">Dashboard</x-ui.action>
                @foreach (['csv' => 'Export CSV', 'excel' => 'Export Excel', 'pdf' => 'Export PDF'] as $format => $label)
                    <x-ui.action :href="route('governance.report-register.index', [...$exportQuery, 'format' => $format])">{{ $label }}</x-ui.action>
                @endforeach
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card title="Report filters" eyebrow="Current view" :meta="number_format(count($page->rows)).' record(s)'">
            <form method="GET" action="{{ route('governance.report-register.index') }}" class="blade-filter-grid">
                <x-forms.field name="report" label="Report">
                    <x-forms.select name="report">
                        @foreach ($page->reports as $value => $label)
                            <option value="{{ $value }}" @selected($page->report === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-forms.field>

                <x-forms.field name="project_id" label="Project">
                    <x-forms.select name="project_id">
                        <option value="">All projects</option>
                        @foreach ($page->projects as $project)
                            <option value="{{ $project['id'] }}" @selected((int) ($page->filters['project_id'] ?? 0) === $project['id'])>
                                {{ $project['code'] }} · {{ $project['name'] }}
                            </option>
                        @endforeach
                    </x-forms.select>
                </x-forms.field>

                <x-forms.field name="status" label="Status">
                    <x-forms.input name="status" :value="$page->filters['status'] ?? ''" placeholder="Optional status" />
                </x-forms.field>

                <x-forms.field name="date_from" label="From">
                    <x-forms.input name="date_from" type="date" :value="$page->filters['date_from'] ?? ''" />
                </x-forms.field>

                <x-forms.field name="date_to" label="To">
                    <x-forms.input name="date_to" type="date" :value="$page->filters['date_to'] ?? ''" />
                </x-forms.field>

                <div class="blade-form-actions">
                    <x-ui.action type="submit">Apply filters</x-ui.action>
                    <x-ui.action :href="route('governance.report-register.index')">Reset</x-ui.action>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :title="$page->reports[$page->report] ?? str($page->report)->headline()" eyebrow="Report register" :meta="number_format(count($page->rows)).' rows'">
            @if ($page->rows === [])
                <x-ui.empty-state title="No report records" description="No records match the selected report filters." icon="fa-chart-column" />
            @else
                <x-ui.responsive-register label="{{ $page->reports[$page->report] ?? 'Report' }} records">
                    <x-slot:desktop>
                        <table class="blade-dashboard-table">
                            <thead><tr>@foreach ($columns as $column)<th scope="col">{{ str($column)->replace('_', ' ')->headline() }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($page->rows as $row)
                                    <tr>@foreach ($columns as $column)<td>{{ is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column] ?? []) }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:desktop>
                    <x-slot:mobile>
                        <div class="b360-mobile-register">
                            @foreach ($page->rows as $row)
                                <article>
                                    @foreach ($columns as $column)
                                        <span><strong>{{ str($column)->replace('_', ' ')->headline() }}:</strong> {{ is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column] ?? []) }}</span>
                                    @endforeach
                                </article>
                            @endforeach
                        </div>
                    </x-slot:mobile>
                </x-ui.responsive-register>
            @endif
        </x-ui.card>
    </div>
@endsection

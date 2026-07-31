@extends('layouts.builder360-classic')

@section('title', $rule->name.' v'.$rule->version.' | Builder360')

@section('content')
    <x-ui.page-header eyebrow="Scoring Logic" :title="$rule->name.' · Version '.$rule->version" :description="$rule->changeReason">
        <x-slot:actions>
            <x-ui.action :href="route('scoring.rules.export', $rule->id)">Export rule</x-ui.action>
            <x-ui.action :href="route('scoring.index', ['view' => 'rule-history'])">Back to rule history</x-ui.action>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="b360-stat-grid" aria-label="Rule version summary">
        @foreach ([['Status', $rule->status, 'Current lifecycle state'], ['Eligible records', $rule->eligibleRecords, $rule->impactLabel], ['Preserved records', $rule->preservedRecords, 'Historical decisions remain unchanged'], ['Version', 'v'.$rule->version, $rule->effectiveAt]] as [$label, $value, $sub])
            <article class="b360-stat-card"><span class="b360-stat-label">{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $sub }}</small></article>
        @endforeach
    </section>

    <div class="b360-dashboard-grid">
        <x-ui.card title="Version evidence" eyebrow="Stored configuration">
            <div class="b360-data-row"><span><strong>Rule key</strong></span><em>{{ $rule->ruleKey }}</em></div>
            <div class="b360-data-row"><span><strong>Created by</strong></span><em>{{ $rule->createdBy }}</em></div>
            <div class="b360-data-row"><span><strong>Effective</strong></span><em>{{ $rule->effectiveAt }}</em></div>
            <div class="b360-data-row"><span><strong>Checksum</strong></span><code>{{ $rule->checksum }}</code></div>
        </x-ui.card>
        <x-ui.card title="Compare versions" eyebrow="Change inspection">
            <form method="GET" action="{{ route('scoring.rules.show', $rule->id) }}" class="blade-inline-form">
                <label for="compare_to">Compare with</label>
                <select id="compare_to" name="compare_to" class="b360-control">
                    <option value="">Select version</option>
                    @foreach ($rule->versions as $version)
                        @if ($version['id'] !== $rule->id)<option value="{{ $version['id'] }}" @selected(request('compare_to') == $version['id'])>Version {{ $version['version'] }} · {{ $version['status'] }}</option>@endif
                    @endforeach
                </select>
                <x-ui.action type="submit">Compare</x-ui.action>
            </form>
            @if ($rule->comparedVersion)
                <p>Compared with version {{ $rule->comparedVersion }}.</p>
                @forelse ($rule->differences as $difference)
                    <div class="b360-data-row"><span><strong>{{ $difference['section'] }}</strong></span><em>Changed</em></div>
                @empty
                    <x-ui.empty-state title="No configuration changes" description="These versions have the same structured settings." icon="fa-equals" />
                @endforelse
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Criteria and weights" eyebrow="Calculation structure" meta="{{ count($rule->criteria) }} criteria">
        <x-ui.responsive-register label="Scoring criteria">
            <x-slot:desktop><table class="blade-data-table"><thead><tr><th>Criterion</th><th>Key</th><th>Weight</th><th>Maximum points</th><th>Input scale</th><th>Conditions</th></tr></thead><tbody>
                @foreach ($rule->criteria as $criterion)<tr><td><strong>{{ $criterion['label'] }}</strong></td><td>{{ $criterion['key'] }}</td><td>{{ $criterion['weight'] }}%</td><td>{{ $criterion['max_points'] }}</td><td>{{ data_get($criterion, 'input_scale.min', 0) }}&ndash;{{ data_get($criterion, 'input_scale.max', $rule->ratingMax) }}</td><td>{{ count($criterion['conditions'] ?? []) }}</td></tr>@endforeach
            </tbody></table></x-slot:desktop>
            <x-slot:mobile><div class="b360-mobile-register">@foreach ($rule->criteria as $criterion)<article><strong>{{ $criterion['label'] }}</strong><span>{{ $criterion['weight'] }}% · {{ $criterion['max_points'] }} points</span></article>@endforeach</div></x-slot:mobile>
        </x-ui.responsive-register>
    </x-ui.card>

    <div class="b360-dashboard-grid">
        <x-ui.card title="Score bands" meta="{{ count($rule->bands) }} bands">
            @foreach ($rule->bands as $band)<div class="b360-data-row"><span><strong>{{ $band['label'] }}</strong><small>{{ $band['outcome'] }}</small></span><em>{{ $band['min_score'] }}+</em></div>@endforeach
        </x-ui.card>
        <x-ui.card title="Rule activity" meta="{{ count($rule->activity) }} events">
            @forelse ($rule->activity as $event)<div class="b360-data-row"><span><strong>{{ $event['event'] }}</strong><small>{{ $event['actor'] }}</small></span><em>{{ $event['at'] }}</em></div>@empty
                <x-ui.empty-state title="No activity recorded" description="Rule lifecycle events will appear here." icon="fa-clock-rotate-left" />
            @endforelse
        </x-ui.card>
    </div>
@endsection

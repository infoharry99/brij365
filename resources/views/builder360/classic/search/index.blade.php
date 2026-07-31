@extends('layouts.builder360-classic')

@section('title', 'Search | Builder360')

@section('content')
    <x-ui.page-header
        eyebrow="Workspace"
        title="Search"
        description="{{ $page->total }} {{ Str::plural('result', $page->total) }} for “{{ $page->query }}”"
    />

    <section class="b360-search-results" aria-label="Search results">
        @foreach ($page->groups as $group)
            @continue(count($group->results) === 0)
            <x-ui.card title="{{ $group->label }}" meta="{{ count($group->results) }} found">
                <div class="b360-search-result-list">
                    @foreach ($group->results as $result)
                        <a class="b360-search-result" href="{{ $result->url }}">
                            <span class="b360-search-result-icon" aria-hidden="true">
                                <i class="fa-solid {{ $result->icon }}"></i>
                            </span>
                            <span>
                                <strong>{{ $result->title }}</strong>
                                <small>{{ $result->subtitle }}</small>
                            </span>
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        @if ($page->total === 0)
            <x-ui.empty-state
                icon="fa-magnifying-glass"
                title="No matching records"
                description="Try a project code, unit number, lead name, or voucher number."
            />
        @endif
    </section>
@endsection

@extends('layouts.builder360-classic')

@section('title', 'Partner Portal - Builder360 ERP-CRM')

@section('content')
@php
        $scope = $summary['scope'] ?? ['partners' => [], 'partner_ids' => []];
        $metrics = $summary['metrics'] ?? [];
        $partners = collect($scope['partners'] ?? []);
        $leadStageSummary = collect($summary['lead_stage_summary'] ?? []);
        $leads = collect($summary['my_leads'] ?? []);
        $siteVisits = collect($summary['site_visits'] ?? []);
        $bookings = collect($summary['bookings'] ?? []);
        $collections = collect($summary['collections_follow_up'] ?? []);
        $commissionSummary = $summary['commission_summary'] ?? ['items' => []];
        $commissionItems = collect($commissionSummary['items'] ?? []);
        $documents = collect($summary['documents'] ?? []);
    @endphp

    <div class="blade-workspace" aria-labelledby="partner-portal-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Partner Channel</p>
                <h1 id="partner-portal-title">Partner Portal</h1>
                <p>
                    Secure partner workspace for available leads, site visits, bookings,
                    collection follow-up, commission visibility and booking/partner document downloads.
                </p>
            </div>
            @include('partner.partials.navigation')
        </header>

        <section class="blade-dashboard-kpis" aria-label="Partner portal KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Leads</span>
                <strong>{{ number_format((int) ($metrics['leads'] ?? 0)) }}</strong>
                <small>{{ number_format((int) ($metrics['open_leads'] ?? 0)) }} open</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Site Visits</span>
                <strong>{{ number_format((int) ($metrics['site_visits'] ?? 0)) }}</strong>
                <small>Available visits</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Bookings</span>
                <strong>{{ number_format((int) ($metrics['bookings'] ?? 0)) }}</strong>
                <small>Partner-attributed</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Open Collections</span>
                <strong>₹{{ number_format((float) ($metrics['open_collection_amount'] ?? 0), 2) }}</strong>
                <small>Follow-up amount</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Scope</span>
                        <h2>Partner profile scope</h2>
                    </div>
                    <small>{{ $partners->count() }} active</small>
                </div>
                <div class="blade-list">
                    @forelse ($partners as $partner)
                        <div class="blade-list-row">
                            <div>
                                <strong>{{ $partner['code'] }} · {{ $partner['name'] }}</strong>
                                <span>{{ $partner['type'] }} · {{ $partner['status'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="blade-muted">No active partner record is linked to this login.</p>
                    @endforelse
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Pipeline</span>
                        <h2>Lead stage summary</h2>
                    </div>
                    <small>{{ $leadStageSummary->count() }} stage(s)</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Stage</th>
                                <th scope="col">Leads</th>
                                <th scope="col">Expected Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leadStageSummary as $stage)
                                <tr>
                                    <td><strong>{{ $stage['stage'] }}</strong></td>
                                    <td>{{ number_format((int) $stage['lead_count']) }}</td>
                                    <td>₹{{ number_format((float) $stage['expected_value_total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">No lead stages found for this partner.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Leads</span>
                    <h2>My leads</h2>
                </div>
                <small>{{ $leads->count() }} shown</small>
            </div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Lead</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Project</th>
                            <th scope="col">Value</th>
                            <th scope="col">Stage</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            <tr>
                                <td>
                                    <strong>{{ $lead['lead_code'] }}</strong>
                                    <span>{{ $lead['source'] }}</span>
                                </td>
                                <td>{{ $lead['customer'] ?? '—' }}</td>
                                <td>{{ $lead['project'] ?? '—' }}</td>
                                <td>₹{{ number_format((float) $lead['expected_value'], 2) }}</td>
                                <td>{{ $lead['stage'] }}</td>
                                <td><span class="blade-status-pill">{{ $leadStatuses[$lead['status']] ?? $lead['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No leads found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Site Visits</span>
                        <h2>Upcoming and recent visits</h2>
                    </div>
                    <small>{{ $siteVisits->count() }} shown</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Visit</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Project</th>
                                <th scope="col">Schedule</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($siteVisits as $visit)
                                <tr>
                                    <td>
                                        <strong>{{ $visit['visit_number'] }}</strong>
                                        <span>{{ $visit['visit_mode'] }}</span>
                                    </td>
                                    <td>{{ $visit['customer'] ?? '—' }}</td>
                                    <td>{{ $visit['project'] ?? '—' }}</td>
                                    <td>{{ $visit['scheduled_at'] ?? '—' }}</td>
                                    <td><span class="blade-status-pill">{{ $visit['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No site visits found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Bookings</span>
                        <h2>Attributed bookings</h2>
                    </div>
                    <small>{{ $bookings->count() }} shown</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Booking</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Unit</th>
                                <th scope="col">Net Receivable</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td>
                                        <strong>{{ $booking['booking_code'] }}</strong>
                                        <span>{{ $booking['project'] ?? '—' }}</span>
                                    </td>
                                    <td>{{ $booking['customer'] ?? '—' }}</td>
                                    <td>{{ $booking['unit'] ?? '—' }}</td>
                                    <td>₹{{ number_format((float) $booking['net_receivable'], 2) }}</td>
                                    <td><span class="blade-status-pill">{{ $bookingStatuses[$booking['status']] ?? $booking['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No bookings found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Collections</span>
                        <h2>Collection follow-up</h2>
                    </div>
                    <small>{{ $collections->count() }} milestone(s)</small>
                </div>
                <div class="blade-dashboard-table-wrap">
                    <table class="blade-dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Milestone</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Due</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($collections as $collection)
                                <tr>
                                    <td>
                                        <strong>{{ $collection['booking_code'] }}</strong>
                                        <span>{{ $collection['milestone'] }}</span>
                                    </td>
                                    <td>{{ $collection['customer'] ?? '—' }}</td>
                                    <td>{{ $collection['due_on'] ?? '—' }}</td>
                                    <td>₹{{ number_format((float) $collection['amount'], 2) }}</td>
                                    <td><span class="blade-status-pill">{{ $collection['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No collection follow-up milestones found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Commission</span>
                        <h2>Commission summary</h2>
                    </div>
                    <small>{{ number_format((int) ($commissionSummary['total_items'] ?? 0)) }} item(s)</small>
                </div>
                <dl class="blade-definition-list">
                    <div>
                        <dt>Approved</dt>
                        <dd>₹{{ number_format((float) ($commissionSummary['approved_amount'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Pending</dt>
                        <dd>₹{{ number_format((float) ($commissionSummary['pending_amount'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Paid / Payroll Included</dt>
                        <dd>₹{{ number_format((float) ($commissionSummary['paid_amount'] ?? 0), 2) }}</dd>
                    </div>
                </dl>
                <div class="blade-list">
                    @forelse ($commissionItems as $item)
                        <div class="blade-list-row">
                            <div>
                                <strong>{{ $item['run_number'] ?? 'Commission Item' }} · {{ $item['period'] }}</strong>
                                <span>{{ $item['booking_code'] ?? $item['lead_code'] ?? '—' }} · ₹{{ number_format((float) $item['commission_amount'], 2) }}</span>
                            </div>
                            <span class="blade-status-pill">{{ $commissionStatuses[$item['status']] ?? $item['status'] }}</span>
                        </div>
                    @empty
                        <p class="blade-muted">No commission items found.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Documents</span>
                    <h2>Partner-visible documents</h2>
                </div>
                <small>{{ $documents->count() }} shown</small>
            </div>
            <div class="blade-list">
                @forelse ($documents as $document)
                    <div class="blade-list-row">
                        <div>
                            <strong>{{ $document['document_number'] }} · {{ $document['title'] }}</strong>
                            <span>{{ $document['category'] ?? 'Document' }} · {{ $document['owner_type'] }} · v{{ $document['version'] }}</span>
                        </div>
                        <a href="{{ $document['download_url'] }}">Download</a>
                    </div>
                @empty
                    <p class="blade-muted">No partner-visible approved documents found.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

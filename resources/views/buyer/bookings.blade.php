@extends('layouts.builder360-classic')

@section('title', 'My Bookings - Builder360 ERP-CRM')

@section('content')
<div class="blade-workspace" aria-labelledby="buyer-bookings-title">
    <header class="blade-workspace-header">
        <div><p class="blade-dashboard-eyebrow">Buyer Portal</p><h1 id="buyer-bookings-title">My Bookings</h1><p>Booking, project, unit and payment schedule details available to your account.</p></div>
        @include('buyer.partials.navigation')
    </header>
    <section class="blade-dashboard-card">
        <form method="GET" action="{{ route('buyer.bookings.index') }}" class="blade-filter-grid blade-filter-grid-compact">
            <label>Status<select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="blade-secondary-action" type="submit">Apply filter</button>
            <a class="blade-secondary-action" href="{{ route('buyer.bookings.index') }}">Reset</a>
        </form>
    </section>
    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Bookings</span><h2>Booking register</h2></div><small>{{ $records->total() }} record(s)</small></div>
        <div class="blade-dashboard-table-wrap"><table class="blade-dashboard-table"><thead><tr><th>Booking</th><th>Project</th><th>Unit</th><th>Booked On</th><th>Net Receivable</th><th>Status</th></tr></thead><tbody>
        @forelse($records as $booking)<tr><td><strong>{{ $booking->booking_code }}</strong></td><td>{{ $booking->project?->name ?? '—' }}</td><td>{{ $booking->unit?->unit_code ?? '—' }}</td><td>{{ $booking->booked_on?->format('d M Y') ?? '—' }}</td><td>₹{{ number_format((float) $booking->net_receivable, 2) }}</td><td><span class="blade-status-pill">{{ $statuses[$booking->status] ?? ucfirst($booking->status) }}</span></td></tr>@empty<tr><td colspan="6">No bookings are available for this account.</td></tr>@endforelse
        </tbody></table></div><div class="blade-pagination">{{ $records->links() }}</div>
    </section>
</div>
@endsection

@extends('layouts.builder360-classic')
@section('title', 'My Receipts - Builder360 ERP-CRM')
@section('content')
<div class="blade-workspace" aria-labelledby="buyer-receipts-title">
<header class="blade-workspace-header"><div><p class="blade-dashboard-eyebrow">Buyer Portal</p><h1 id="buyer-receipts-title">My Receipts</h1><p>Approved payment receipts linked to your bookings.</p></div>@include('buyer.partials.navigation')</header>
<section class="blade-dashboard-card"><div class="blade-dashboard-section-title"><div><span class="blade-dashboard-label">Receipts</span><h2>Approved receipts</h2></div><small>{{ $records->total() }} record(s)</small></div><div class="blade-dashboard-table-wrap"><table class="blade-dashboard-table"><thead><tr><th>Receipt</th><th>Booking</th><th>Project</th><th>Date</th><th>Mode</th><th>Amount</th></tr></thead><tbody>
@forelse($records as $receipt)<tr><td><strong>{{ $receipt->receipt_number }}</strong></td><td>{{ $receipt->booking?->booking_code ?? '—' }}</td><td>{{ $receipt->project?->name ?? '—' }}</td><td>{{ $receipt->receipt_date?->format('d M Y') ?? '—' }}</td><td>{{ strtoupper($receipt->payment_mode) }}</td><td>₹{{ number_format((float) $receipt->amount, 2) }}</td></tr>@empty<tr><td colspan="6">No approved receipts are available.</td></tr>@endforelse
</tbody></table></div><div class="blade-pagination">{{ $records->links() }}</div></section></div>
@endsection

@extends('errors.layout')

@section('title', '419 - Page Expired')

@section('glow-color-1', '#F59E0B')
@section('glow-color-2', '#EF4444')

@section('code-gradient', 'linear-gradient(135deg, #FBBF24 0%, #F87171 100%)')
@section('icon-bg', 'rgba(245, 158, 11, 0.15)')
@section('icon-border', 'rgba(245, 158, 11, 0.3)')
@section('icon-color', '#FBBF24')
@section('btn-bg', 'linear-gradient(135deg, #D97706 0%, #DC2626 100%)')

@section('icon')
    <i class="fa-solid fa-clock-rotate-left"></i>
@endsection

@section('code', '419')
@section('headline', 'Page Session Expired')
@section('message', 'Your session timed out due to inactivity or a security token mismatch. Refresh the page to log back in or continue your work.')

@section('actions')
    <button type="button" class="btn-primary" onclick="window.location.reload()">
        <i class="fa-solid fa-rotate"></i> Refresh Page
    </button>
    <a href="{{ url('/') }}" class="btn-secondary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
@endsection

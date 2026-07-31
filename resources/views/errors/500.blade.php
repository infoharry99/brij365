@extends('errors.layout')

@section('title', '500 - Server Error')

@section('glow-color-1', '#8B5CF6')
@section('glow-color-2', '#EC4899')

@section('code-gradient', 'linear-gradient(135deg, #A78BFA 0%, #F472B6 100%)')
@section('icon-bg', 'rgba(139, 92, 246, 0.15)')
@section('icon-border', 'rgba(139, 92, 246, 0.3)')
@section('icon-color', '#A78BFA')
@section('btn-bg', 'linear-gradient(135deg, #7C3AED 0%, #DB2777 100%)')

@section('icon')
    <i class="fa-solid fa-triangle-exclamation"></i>
@endsection

@section('code', '500')
@section('headline', 'Internal Server Error')
@section('message', 'An unexpected server error occurred while processing your request. Please try again or return to the dashboard while our engineers investigate.')

@section('actions')
    <button type="button" class="btn-primary" onclick="window.location.reload()">
        <i class="fa-solid fa-rotate-right"></i> Try Again
    </button>
    <a href="{{ url('/') }}" class="btn-secondary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
@endsection

@extends('errors.layout')

@section('title', '403 - Access Forbidden')

@section('glow-color-1', '#F43F5E')
@section('glow-color-2', '#E11D48')

@section('code-gradient', 'linear-gradient(135deg, #FB7185 0%, #F43F5E 100%)')
@section('icon-bg', 'rgba(244, 63, 94, 0.15)')
@section('icon-border', 'rgba(244, 63, 94, 0.3)')
@section('icon-color', '#FB7185')
@section('btn-bg', 'linear-gradient(135deg, #E11D48 0%, #BE123C 100%)')

@section('icon')
    <i class="fa-solid fa-shield-halved"></i>
@endsection

@section('code', '403')
@section('headline', 'Access Forbidden')
@section('message', 'You do not have permission to access this page or resource. Contact your system administrator if you believe your account role requires access.')

@section('actions')
    <a href="{{ url('/') }}" class="btn-primary">
        <i class="fa-solid fa-house"></i> Return to Dashboard
    </a>
    <button type="button" class="btn-secondary" onclick="window.history.back()">
        <i class="fa-solid fa-arrow-left"></i> Go Back
    </button>
@endsection

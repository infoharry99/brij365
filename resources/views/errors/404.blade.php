@extends('errors.layout')

@section('title', '404 - Page Not Found')

@section('glow-color-1', '#3B82F6')
@section('glow-color-2', '#6366F1')

@section('code-gradient', 'linear-gradient(135deg, #60A5FA 0%, #818CF8 100%)')
@section('icon-bg', 'rgba(59, 130, 246, 0.15)')
@section('icon-border', 'rgba(59, 130, 246, 0.3)')
@section('icon-color', '#60A5FA')
@section('btn-bg', 'linear-gradient(135deg, #2563EB 0%, #4F46E5 100%)')

@section('icon')
    <i class="fa-solid fa-compass"></i>
@endsection

@section('code', '404')
@section('headline', 'Page Not Found')
@section('message', 'The page you are looking for might have been removed, renamed, or is temporarily unavailable. Check the web address or return home.')

@section('actions')
    <a href="{{ url('/') }}" class="btn-primary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
    <button type="button" class="btn-secondary" onclick="window.history.back()">
        <i class="fa-solid fa-arrow-left"></i> Go Back
    </button>
@endsection

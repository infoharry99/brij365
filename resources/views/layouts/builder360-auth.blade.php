<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Builder360 ERP CRM')</title>
    @vite(['resources/css/enterprise.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="b360-classic b360-auth-body">
    @include('partials.brij-loader')
    @yield('content')
    @stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full min-h-screen">
    @inertia
</body>
</html>

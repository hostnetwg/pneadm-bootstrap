<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-light text-dark">
        <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center pt-4 bg-light">
            <div class="mb-4">
                <a href="/">
                    <x-application-logo class="d-block" style="width: 80px; height: 80px; color: #6c757d;" />
                </a>
            </div>

            <div class="w-100" style="max-width: 400px;">
                <div class="card shadow-sm">
                    <div class="card-body">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

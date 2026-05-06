<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Bolão Copa') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased min-h-screen flex flex-col items-center justify-center bg-pitch-950 p-4">

    {{-- Background decoration --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-900/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-blue-900/20 blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
        {{-- Logo --}}
        <div class="mb-8 flex flex-col items-center gap-3">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-3xl shadow-lg shadow-emerald-900/50">
                ⚽
            </div>
            <div class="text-center">
                <h1 class="text-2xl font-bold text-white">Bolão Copa do Mundo</h1>
                <p class="text-sm text-slate-400 mt-1">2026 · USA · Canada · México</p>
            </div>
        </div>

        {{-- Content --}}
        <div class="card p-8 shadow-2xl shadow-black/40">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>

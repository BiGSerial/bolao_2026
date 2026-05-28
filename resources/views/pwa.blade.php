<!DOCTYPE html>
<html lang="pt-BR" class="h-full" style="background:#0d0f12;color:#e2e8f0">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d0f12">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'BolãoVF') }}">
    <title>{{ config('app.name', 'BolãoVF') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @vite(['resources/css/pwa.css', 'resources/js/pwa/main.js'])
</head>
<body class="h-full" style="background:#0d0f12;margin:0">
    <div id="pwa-app" class="h-full"></div>
</body>
</html>

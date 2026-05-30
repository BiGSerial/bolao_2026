<!DOCTYPE html>
<html lang="pt-BR" class="h-full" style="background:#0d0f12;color:#e2e8f0">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d0f12">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'BolãoVF') }}">
    <title>{{ config('app.name', 'BolãoVF') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/pwa.css', 'resources/js/pwa/main.js'])
    @endif
</head>
<body class="h-full" style="background:#0d0f12;margin:0">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        <div id="pwa-app" class="h-full"></div>
    @else
        <main style="min-height:100%;display:grid;place-items:center;padding:24px;">
            <section style="max-width:420px;width:100%;border:1px solid rgba(245,166,35,.28);border-radius:14px;background:#111827;padding:20px;color:#e5e7eb;font-family:Barlow,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
                <h1 style="margin:0 0 10px 0;font-size:22px;line-height:1.1;color:#f8fafc;font-family:'Barlow Condensed',Barlow,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">Aplicativo indisponível</h1>
                <p style="margin:0 0 10px 0;line-height:1.5;color:#cbd5e1;">Os arquivos da versão web do aplicativo não foram publicados neste ambiente.</p>
                <p style="margin:0;line-height:1.5;color:#fbbf24;">Publique o build da PWA e recarregue a página.</p>
            </section>
        </main>
    @endif
</body>
</html>

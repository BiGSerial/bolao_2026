<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@isset($csrf)
<meta name="csrf-token" content="{{ csrf_token() }}">
@endisset
<title>{{ $title ?? config('app.name', 'BolãoVF') }}</title>
<meta name="theme-color" content="#0b1017">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'BolãoVF') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<script>
    (() => {
        const hasConsent = document.cookie.split('; ').some((cookie) => cookie.startsWith('cookie_consent=accepted'));
        if (!hasConsent) {
            document.documentElement.classList.add('cookie-consent-locked');
        }
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@if(($includeLivewireStyles ?? true) === true)
@livewireStyles
@endif

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@isset($csrf)
<meta name="csrf-token" content="{{ csrf_token() }}">
@endisset
<title>{{ $title ?? config('app.name', 'Bolão Copa') }}</title>
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
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

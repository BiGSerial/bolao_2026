<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@isset($csrf)
<meta name="csrf-token" content="{{ csrf_token() }}">
@endisset
<title>{{ $title ?? config('app.name', 'Bolão Copa') }}</title>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@if(($includeLivewireStyles ?? true) === true)
@livewireStyles
@endif

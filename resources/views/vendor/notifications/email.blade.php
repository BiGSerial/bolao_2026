<x-mail::message>
{{-- Saudação --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Ops!
@else
# Olá!
@endif
@endif

{{-- Linhas de introdução --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Botão de ação --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Linhas de encerramento --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Assinatura --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Atenciosamente,<br>
{{ config('app.name') }}
@endif

{{-- Subcópia --}}
@isset($actionText)
<x-slot:subcopy>
Se você está com dificuldades para clicar no botão "{{ $actionText }}", copie e cole o endereço abaixo no seu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background: #020f2b; }
        table { border-collapse: collapse; }
    </style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#020f2b; padding:24px 0;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px; margin:0 auto; background:#071b3f; border:1px solid #1e3a6a; border-radius:14px; overflow:hidden;">
                {{ $header ?? '' }}

                <tr>
                    <td style="padding:32px 32px 24px 32px; color:#dbeafe; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.7;">
                        {{ Illuminate\Mail\Markdown::parse($slot) }}
                    </td>
                </tr>

                @if(!empty(trim((string)($subcopy ?? ''))))
                <tr>
                    <td style="padding:0 32px 20px 32px; border-top:1px dashed #1e3a6a;">
                        <div style="padding-top:14px; color:#7dd3fc; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6;">
                            {{ $subcopy }}
                        </div>
                    </td>
                </tr>
                @endif

                {{ $footer ?? '' }}
            </table>
        </td>
    </tr>
</table>
</body>
</html>

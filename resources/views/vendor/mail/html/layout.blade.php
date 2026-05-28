<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background: #080a0d; }
        table { border-collapse: collapse; }
        a { color: #f5a623; }
        .mail-root {
            font-family: "Barlow", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #e5e7eb;
        }
        .mail-content h1,
        .mail-content h2,
        .mail-content h3 {
            margin: 0 0 14px 0;
            color: #f8fafc;
            font-family: "Barlow Condensed", "Barlow", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-weight: 800;
            letter-spacing: .2px;
        }
        .mail-content h1 { font-size: 30px; line-height: 1.1; }
        .mail-content h2 { font-size: 24px; line-height: 1.2; }
        .mail-content p {
            margin: 0 0 14px 0;
            color: #e2e8f0;
            font-size: 16px;
            line-height: 1.65;
        }
        .mail-content strong { color: #f8fafc; }
        .mail-content ul,
        .mail-content ol {
            margin: 0 0 14px 20px;
            color: #e2e8f0;
        }
        .mail-content li { margin: 0 0 6px 0; }
        .mail-content .button,
        .mail-content .button-primary {
            background: #f5a623 !important;
            border-color: #f5a623 !important;
            color: #0b1017 !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-family: "Barlow", "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        }
        .mail-content .button a,
        .mail-content .button-primary a {
            color: #0b1017 !important;
        }
    </style>
</head>
<body class="mail-root">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#080a0d; padding:24px 0;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px; margin:0 auto; background:#111827; border:1px solid rgba(245,166,35,0.24); border-radius:14px; overflow:hidden;">
                {{ $header ?? '' }}

                <tr>
                    <td class="mail-content" style="padding:32px 32px 24px 32px; color:#e5e7eb; font-family:'Barlow','Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.7;">
                        {{ Illuminate\Mail\Markdown::parse($slot) }}
                    </td>
                </tr>

                @if(!empty(trim((string)($subcopy ?? ''))))
                <tr>
                    <td style="padding:0 32px 20px 32px; border-top:1px dashed rgba(245,166,35,0.24);">
                        <div style="padding-top:14px; color:#fbbf24; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6;">
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

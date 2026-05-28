@props(['url'])
@php($brandName = 'BolãoVF')
<tr>
    <td style="padding:18px 24px; border-bottom:1px solid rgba(245,166,35,0.24); background:linear-gradient(90deg,#0f172a,#111827);">
        <a href="{{ $url }}" style="text-decoration:none; font-family:'Barlow','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
            <img
                src="{{ rtrim((string) config('app.url'), '/') . '/favicon.png' }}"
                alt="{{ $brandName }}"
                width="34"
                height="34"
                style="display:inline-block; vertical-align:middle; width:34px; height:34px; object-fit:contain; margin-right:10px;"
            >
            <span style="vertical-align:middle; color:#f8fafc; font-size:20px; font-weight:800; letter-spacing:-0.3px;">
                Bolão<span style="color:#f5a623;">VF</span>
            </span>
        </a>
    </td>
</tr>

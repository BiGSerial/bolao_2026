@props(['url'])
<tr>
    <td style="padding:20px 28px; border-bottom:1px solid #1e3a6a; background:linear-gradient(90deg,#05224f,#06316f);">
        <a href="{{ $url }}" style="text-decoration:none; font-family:Arial, Helvetica, sans-serif;">
            <span style="display:inline-block; vertical-align:middle; width:36px; height:36px; line-height:36px; text-align:center; border-radius:10px; background:#10b981; color:#ffffff; font-size:18px; margin-right:10px;">⚽</span>
            <span style="vertical-align:middle; color:#10b981; font-size:20px; font-weight:700;">{{ config('app.name', 'Bolão') }}</span>
        </a>
    </td>
</tr>


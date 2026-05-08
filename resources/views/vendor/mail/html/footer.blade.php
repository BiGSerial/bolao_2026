<tr>
    <td style="padding:16px 28px 22px 28px; border-top:1px solid #1e3a6a; color:#93c5fd; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.5;">
        @if(isset($slot) && trim((string) $slot) !== '')
        <div style="margin-bottom:8px;">
            {!! Illuminate\Mail\Markdown::parse($slot) !!}
        </div>
        @endif
        <div style="margin-bottom:8px;">
            Plataforma recreativa de organização de palpites esportivos entre usuários.
        </div>
        <div style="color:#7dd3fc;">
            © {{ date('Y') }} VixForge Sistemas. Todos os direitos reservados.
        </div>
    </td>
</tr>

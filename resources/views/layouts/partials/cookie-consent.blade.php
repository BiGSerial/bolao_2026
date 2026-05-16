<style>
    .cc-overlay {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.82);
        backdrop-filter: blur(2px);
        z-index: 99998;
    }
    .cc-banner {
        position: fixed;
        left: 16px;
        right: 16px;
        bottom: 16px;
        z-index: 99999;
        background: #0f172a;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 14px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
        padding: 14px;
        color: #e2e8f0;
        font-family: inherit;
    }
    .cc-title {
        margin: 0 0 6px 0;
        font-size: 14px;
        font-weight: 700;
        color: #f8fafc;
    }
    .cc-text {
        margin: 0;
        font-size: 13px;
        line-height: 1.45;
        color: #cbd5e1;
    }
    .cc-actions {
        margin-top: 12px;
        display: flex;
        justify-content: flex-end;
    }
    .cc-btn {
        border: 0;
        border-radius: 10px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        color: #0b1220;
        background: linear-gradient(135deg, #f5a623, #e8390d);
    }
    html.cookie-consent-locked,
    body.cookie-consent-locked {
        overflow: hidden !important;
    }
    @media (min-width: 768px) {
        .cc-banner {
            left: 24px;
            right: 24px;
            bottom: 24px;
            max-width: 760px;
            margin: 0 auto;
        }
    }
</style>

<div id="cookie-consent-overlay" class="cc-overlay" style="display:none"></div>
<div id="cookie-consent-banner" class="cc-banner" style="display:none" role="dialog" aria-modal="true" aria-labelledby="cc-title">
    <p id="cc-title" class="cc-title">Consentimento de cookies</p>
    <p class="cc-text">
        Utilizamos cookies para manter sua sessão ativa, reforçar a segurança, lembrar preferências e medir uso do site para melhorias.
        Ao clicar em “Aceitar e continuar”, você concorda com esse uso.
    </p>
    <div class="cc-actions">
        <button id="cookie-consent-accept" type="button" class="cc-btn">Aceitar e continuar</button>
    </div>
</div>

<script>
    (() => {
        const overlay = document.getElementById('cookie-consent-overlay');
        const banner = document.getElementById('cookie-consent-banner');
        const acceptBtn = document.getElementById('cookie-consent-accept');
        if (!overlay || !banner || !acceptBtn) return;

        const hasConsent = document.cookie.split('; ').some((cookie) => cookie.startsWith('cookie_consent=accepted'));
        if (hasConsent) {
            document.documentElement.classList.remove('cookie-consent-locked');
            document.body.classList.remove('cookie-consent-locked');
            return;
        }

        overlay.style.display = 'block';
        banner.style.display = 'block';
        document.body.classList.add('cookie-consent-locked');

        acceptBtn.addEventListener('click', () => {
            const maxAge = 60 * 60 * 24 * 365;
            document.cookie = `cookie_consent=accepted; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
            overlay.style.display = 'none';
            banner.style.display = 'none';
            document.documentElement.classList.remove('cookie-consent-locked');
            document.body.classList.remove('cookie-consent-locked');
        });
    })();
</script>

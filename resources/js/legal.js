function renderMarkdown(content, emptyHtml) {
    if (!content) {
        return emptyHtml;
    }

    if (typeof window.marked !== 'undefined') {
        window.marked.setOptions({ breaks: true, gfm: true });
        return window.marked.parse(content);
    }

    return String(content).replace(/\n/g, '<br>');
}

function renderMarkdownWhenReady(targetId, content, emptyHtml = '', attempts = 20) {
    const el = document.getElementById(targetId);
    if (!el) return;

    const raw = (content || '').trim();
    if (!raw) {
        el.innerHTML = emptyHtml;
        return;
    }

    if (typeof window.marked !== 'undefined') {
        el.innerHTML = renderMarkdown(raw, emptyHtml);
        return;
    }

    if (attempts <= 0) {
        el.innerHTML = String(raw).replace(/\n/g, '<br>');
        return;
    }

    window.setTimeout(() => renderMarkdownWhenReady(targetId, raw, emptyHtml, attempts - 1), 50);
}

window.createLegalModalData = function createLegalModalData(config = {}) {
    const docs = config.docs || {};
    const modalBodyId = config.modalBodyId || '';
    const emptyHtml = config.emptyHtml || '<p>Documento não disponível.</p>';

    return {
        legalModal: false,
        legalModalTitle: '',
        legalModalContent: '',
        openLegal(type) {
            const doc = docs[type] || {};
            this.legalModalTitle = doc.title || 'Documento';
            this.legalModalContent = doc.content || '';
            this.legalModal = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                if (!modalBodyId) return;
                const el = document.getElementById(modalBodyId);
                if (el) el.scrollTop = 0;
            });
        },
        closeLegal() {
            this.legalModal = false;
            document.body.style.overflow = '';
        },
        renderMd(content) {
            return renderMarkdown(content, emptyHtml);
        },
    };
};

window.createAcceptanceData = function createAcceptanceData(config = {}) {
    const emptyHtml = config.emptyHtml || '<p>Conteúdo não disponível.</p>';

    return {
        acceptEula: !!config.acceptEula,
        acceptPrivacyPolicy: !!config.acceptPrivacyPolicy,
        activeTab: 'eula',
        eulaContent: config.eulaContent || '',
        privacyContent: config.privacyContent || '',
        renderMd(content) {
            return renderMarkdown(content, emptyHtml);
        },
    };
};

window.renderLegalDocumentContent = function renderLegalDocumentContent(targetId, content) {
    renderMarkdownWhenReady(
        targetId,
        content || '',
        "<p style='color:#94a3b8;font-style:italic;text-align:center;padding:1.2rem 0'>Documento não disponível no momento.</p>",
    );
};

<template>
    <div class="legal-gate">

        <!-- Header -->
        <div class="legal-header">
            <div class="h-8 w-8 rounded-lg bg-bolao-accent/20 flex items-center justify-center shrink-0">
                <i class="ti ti-trophy text-bolao-accent text-base"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-bold text-white leading-tight">BolãoVF</p>
                <p class="text-[11px] text-bolao-muted">Atualização dos termos</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center flex-1 gap-3 text-bolao-muted">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
            <p class="text-sm">Carregando documentos...</p>
        </div>

        <!-- Error loading docs -->
        <div v-else-if="loadError" class="flex flex-col items-center justify-center flex-1 gap-3 px-6 text-center">
            <i class="ti ti-alert-triangle text-3xl text-amber-400"></i>
            <p class="text-sm text-bolao-muted">{{ loadError }}</p>
            <button @click="loadDocuments" class="text-bolao-accent text-sm font-bold">Tentar novamente</button>
        </div>

        <template v-else>
            <!-- Intro -->
            <div class="px-5 pt-4 pb-2">
                <h1 class="text-lg font-bold text-white mb-1">Documentos atualizados</h1>
                <p class="text-sm text-bolao-muted">
                    Nossos termos foram atualizados. Leia e aceite para continuar usando o BolãoVF.
                </p>
            </div>

            <!-- Tab selector -->
            <div class="flex border-b border-white/[0.07] mx-5 mb-0">
                <button
                    v-for="doc in documents"
                    :key="doc.type"
                    @click="activeDoc = doc.type"
                    class="flex-1 py-2.5 text-xs font-bold transition-colors relative"
                    :class="activeDoc === doc.type ? 'text-bolao-accent' : 'text-bolao-muted'"
                >
                    {{ doc.title }}
                    <span
                        v-if="accepted[doc.id]"
                        class="absolute top-1.5 right-2 w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center"
                    >
                        <i class="ti ti-check text-[8px] text-white"></i>
                    </span>
                    <div
                        class="absolute bottom-0 left-0 right-0 h-0.5 rounded-full transition-all"
                        :class="activeDoc === doc.type ? 'bg-bolao-accent' : 'bg-transparent'"
                    ></div>
                </button>
            </div>

            <!-- Document content -->
            <div
                v-for="doc in documents"
                :key="'content-' + doc.type"
                v-show="activeDoc === doc.type"
                class="legal-content"
                :ref="el => { if (el) contentRefs[doc.type] = el }"
                @scroll="onScroll(doc)"
            >
                <div class="legal-meta">
                    <span class="text-[10px] text-bolao-muted2 uppercase tracking-wider">
                        Versão {{ doc.version }}
                    </span>
                    <span class="text-[10px] text-bolao-muted2">
                        {{ formatDate(doc.published_at) }}
                    </span>
                </div>

                <div class="legal-body prose-legal" v-html="renderMarkdown(doc.content)"></div>

                <!-- Read indicator -->
                <div class="read-indicator" :class="scrolledToEnd[doc.type] ? 'read' : ''">
                    <template v-if="scrolledToEnd[doc.type]">
                        <i class="ti ti-check text-emerald-400 text-sm"></i>
                        <span class="text-xs text-emerald-400 font-semibold">Documento lido</span>
                    </template>
                    <template v-else>
                        <i class="ti ti-arrow-down text-bolao-muted2 text-sm animate-bounce"></i>
                        <span class="text-xs text-bolao-muted2">Role até o final para ler</span>
                    </template>
                </div>
            </div>

            <!-- Acceptance checkboxes -->
            <div class="px-5 py-3 space-y-2 border-t border-white/[0.06]">
                <label
                    v-for="doc in documents"
                    :key="'chk-' + doc.id"
                    class="flex items-start gap-3 cursor-pointer"
                    :class="{ 'opacity-40': !scrolledToEnd[doc.type] }"
                >
                    <input
                        type="checkbox"
                        class="legal-checkbox"
                        :disabled="!scrolledToEnd[doc.type]"
                        :checked="accepted[doc.id]"
                        @change="toggleAccept(doc.id, $event.target.checked)"
                    >
                    <span class="text-xs text-slate-300 leading-relaxed">
                        Li e aceito os <strong class="text-white">{{ doc.title }}</strong>
                        <span class="text-bolao-muted2"> (v{{ doc.version }})</span>
                    </span>
                </label>
            </div>

            <!-- CTA -->
            <div class="px-5 pb-6">
                <button
                    @click="submitAcceptance"
                    :disabled="!allAccepted || submitting"
                    class="w-full py-3.5 rounded-xl font-bold text-sm transition-all"
                    :class="allAccepted
                        ? 'bg-bolao-accent text-bolao-bg1 active:opacity-80'
                        : 'bg-bolao-bg4 text-bolao-muted cursor-not-allowed'"
                >
                    <span v-if="submitting" class="flex items-center justify-center gap-2">
                        <i class="ti ti-loader-2 animate-spin text-sm"></i> Registrando aceite...
                    </span>
                    <span v-else>Aceitar e Continuar</span>
                </button>
                <p v-if="submitError" class="text-[12px] text-red-400 text-center mt-2">{{ submitError }}</p>
                <p class="text-center text-[11px] text-bolao-muted2 mt-3">
                    Ao aceitar, seu registro fica guardado com data, hora e versão do documento.
                </p>
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { getLegalDocument, acceptLegalDocuments } from '../api/legal';
import { renderMarkdown } from '../utils/markdown';

const router = useRouter();
const auth   = useAuthStore();

const loading   = ref(true);
const loadError = ref('');
const documents = ref([]);  // [{id, type, title, version, content, published_at}]
const activeDoc = ref('');
const submitting  = ref(false);
const submitError = ref('');

// Track which docs were accepted by the user in this session
const accepted     = reactive({});  // { [doc.id]: boolean }
// Track scroll-to-end state per doc type
const scrolledToEnd = reactive({});
// Refs to content elements
const contentRefs = reactive({});

const allAccepted = computed(() =>
    documents.value.length > 0 &&
    documents.value.every((d) => accepted[d.id])
);

async function loadDocuments() {
    loading.value   = true;
    loadError.value = '';
    try {
        const [eulaRes, privRes] = await Promise.all([
            getLegalDocument('eula'),
            getLegalDocument('privacy-policy'),
        ]);

        const eula    = eulaRes.data.data;
        const privacy = privRes.data.data;
        documents.value = [eula, privacy];
        activeDoc.value = eula.type;

        // Init state
        documents.value.forEach((d) => {
            accepted[d.id]         = false;
            scrolledToEnd[d.type]  = false;
        });
    } catch (err) {
        if (err?.response?.status === 404) {
            // Documentos ainda não publicados — libera o acesso
            await auth.clearLegalPending();
            router.replace('/pwa/dashboard');
        } else {
            loadError.value = 'Não foi possível carregar os documentos. Verifique sua conexão.';
        }
    } finally {
        loading.value = false;
    }
}

function onScroll(doc) {
    const el = contentRefs[doc.type];
    if (!el || scrolledToEnd[doc.type]) return;
    const threshold = 40; // px do fundo
    if (el.scrollHeight - el.scrollTop - el.clientHeight <= threshold) {
        scrolledToEnd[doc.type] = true;
    }
}

function toggleAccept(docId, value) {
    accepted[docId] = value;
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

async function submitAcceptance() {
    if (!allAccepted.value || submitting.value) return;
    submitting.value  = true;
    submitError.value = '';
    try {
        const ids = documents.value.map((d) => d.id);
        const { data: res } = await acceptLegalDocuments(ids);
        await auth.clearLegalPending();
        router.replace('/pwa/dashboard');
    } catch (err) {
        submitError.value = err?.response?.data?.message ?? 'Erro ao registrar aceite. Tente novamente.';
    } finally {
        submitting.value = false;
    }
}

onMounted(loadDocuments);
</script>

<style scoped>
.legal-gate {
    min-height: 100dvh;
    background: #0d0f12;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.legal-header {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: #111318;
    flex-shrink: 0;
}

.legal-content {
    flex: 1;
    overflow-y: auto;
    padding: 0 20px 8px;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    min-height: 0;
}

.legal-meta {
    display: flex;
    justify-content: space-between;
    padding: 10px 0 8px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    margin-bottom: 16px;
}

.legal-body {
    font-size: 13px;
    line-height: 1.7;
    color: #cbd5e1;
}
/* Basic prose styling for HTML content */
.legal-body :deep(h1),
.legal-body :deep(h2) { font-size: 15px; font-weight: 700; color: #f1f5f9; margin: 16px 0 8px; }
.legal-body :deep(h3) { font-size: 13px; font-weight: 700; color: #f1f5f9; margin: 12px 0 6px; }
.legal-body :deep(p)  { margin-bottom: 10px; }
.legal-body :deep(ul),
.legal-body :deep(ol) { padding-left: 18px; margin-bottom: 10px; }
.legal-body :deep(li) { margin-bottom: 4px; }
.legal-body :deep(strong) { color: #f1f5f9; font-weight: 600; }
.legal-body :deep(a) { color: #f5a623; text-decoration: underline; }

.read-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 0 16px;
    border-top: 1px solid rgba(255,255,255,0.05);
    margin-top: 16px;
}

.legal-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 5px;
    accent-color: #f5a623;
    flex-shrink: 0;
    margin-top: 1px;
    cursor: pointer;
}
</style>

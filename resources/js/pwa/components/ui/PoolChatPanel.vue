<template>
    <!-- Raiz com altura dinâmica calculada por JS: evita o fixed que criava a barra preta -->
    <div ref="chatRootEl" class="chat-root" :style="{ height: chatHeight + 'px' }">

        <!-- ── Thread ── -->
        <div ref="listEl" class="chat-messages" @scroll.passive="onListScroll">
            <template v-if="messages.length === 0 && !loading">
                <div class="flex flex-col items-center justify-center h-full gap-2 py-12">
                    <i class="ti ti-message-circle text-4xl text-slate-700"></i>
                    <p class="text-sm text-slate-500">Sem mensagens ainda. Inicie a conversa!</p>
                </div>
            </template>

            <div v-for="item in messages" :key="item.id"
                 class="flex items-end gap-2 mb-1"
                 :class="isMine(item) ? 'justify-end' : 'justify-start'">

                <!-- Avatar para mensagens dos outros -->
                <div v-if="!isMine(item)"
                     class="chat-avatar shrink-0"
                     :style="{ background: avatarBg(item.user?.name) }">
                    {{ initials(item.user?.name) }}
                </div>

                <!-- Swipe hint -->
                <div v-if="swipeHintMessageId === item.id"
                     class="text-[11px] text-bolao-accent flex items-center gap-1 shrink-0">
                    <i class="ti ti-arrow-back-up"></i>
                </div>

                <!-- Bubble -->
                <div class="chat-bubble-wrap"
                     :class="isMine(item) ? 'chat-bubble-wrap-me' : 'chat-bubble-wrap-other'"
                     :style="swipeTranslateStyle(item.id)"
                     @dblclick="toggleReaction(item.id, '👍')"
                     @touchstart="onBubbleTouchStart($event, item)"
                     @touchmove="onBubbleTouchMove($event)"
                     @touchend="onBubbleTouchEnd($event, item)">

                    <!-- Nome (outros) -->
                    <p v-if="!isMine(item)"
                       class="chat-author"
                       :style="{ color: nameColor(item.user?.name) }">
                        {{ item.user?.name || '—' }}
                    </p>

                    <!-- Reply quote -->
                    <div v-if="item.reply_to" class="chat-reply mb-1.5 px-2 py-1">
                        <p class="text-[10px] text-slate-300/80">Respondendo {{ item.reply_to.user_name }}</p>
                        <p class="text-[12px] text-white/80 line-clamp-2">{{ item.reply_to.body }}</p>
                    </div>

                    <!-- Texto + hora na mesma linha ao fim -->
                    <span class="chat-body">{{ item.body }}<span class="chat-meta-spacer">&#8203;&#xFEFF;</span></span>
                    <div class="chat-meta-row">
                        <span class="chat-time">{{ formatLocalTime(item.created_at) }}</span>
                        <span v-if="isMine(item)"
                              class="chat-tick"
                              :class="allReadByOthers(item.id) ? 'chat-tick-read' : 'chat-tick-sent'">
                            <i class="ti ti-checks text-[11px]"></i>
                        </span>
                    </div>

                    <!-- Reactions -->
                    <button v-if="item.reactions?.length"
                            class="chat-reaction-float"
                            @click="toggleReaction(item.id, item.reactions[0].emoji)">
                        <span>{{ item.reactions[0].emoji }}</span>
                        <span v-if="(item.reactions[0].count || 0) > 1" class="chat-reaction-count">{{ item.reactions[0].count }}</span>
                    </button>
                </div>
            </div>

            <!-- Padding final para a última bolha não colar -->
            <div style="height:8px"></div>
        </div>

        <!-- ── Context menu overlay ── -->
        <div v-if="contextMessage"
             class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center"
             @click="contextMessage = null">
            <div class="rounded-2xl border border-white/15 bg-[#0f172a] px-3 py-2 shadow-xl" @click.stop>
                <div class="flex items-center gap-3 pb-2">
                    <button v-for="emoji in quickReactions" :key="emoji" class="text-2xl" @click.stop="pickReaction(emoji)">{{ emoji }}</button>
                </div>
                <div class="border-t border-white/10 pt-2 grid grid-cols-2 gap-2 text-center">
                    <button class="text-xs text-slate-300 py-1" @click.stop="startReply(contextMessage); contextMessage = null">Responder</button>
                    <button class="text-xs text-slate-300 py-1" @click.stop="copyMessage(contextMessage)">Copiar</button>
                </div>
            </div>
        </div>

        <!-- ── Typing indicator ── -->
        <p v-if="typingNames.length" class="typing-bar">{{ typingNames.join(', ') }} digitando...</p>

        <!-- ── Mention suggestions ── -->
        <div v-if="mentionSuggestions.length" class="mention-bar">
            <button v-for="user in mentionSuggestions" :key="user.id"
                    class="text-[11px] rounded-full border border-white/15 px-2 py-0.5 text-slate-200"
                    @click="applyMention(user)">
                @{{ user.name }}
            </button>
        </div>

        <!-- ── Reply preview ── -->
        <div v-if="replyTo" class="reply-preview">
            <div class="flex-1 min-w-0 border-l-2 border-bolao-accent pl-2">
                <p class="text-[11px] font-bold text-bolao-accent">{{ replyTo.user?.name }}</p>
                <p class="text-[12px] text-slate-300 truncate">{{ replyTo.body }}</p>
            </div>
            <button class="shrink-0 text-slate-400 p-1" @click="replyTo = null">
                <i class="ti ti-x text-[15px]"></i>
            </button>
        </div>

        <!-- ── Composer (NÃO é fixed — parte do flex column) ── -->
        <div class="chat-composer">
            <input v-model="draft"
                   class="chat-input flex-1"
                   maxlength="2000"
                   placeholder="Digite uma mensagem"
                   @input="onTypingInput"
                   @keydown.enter.prevent="submit" />
            <button class="chat-send-btn"
                    :disabled="sending || !canSend"
                    @click="submit"
                    aria-label="Enviar">
                <i class="ti ti-send text-[17px]"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { getChatMessages, getChatParticipants, markChatRead, sendChatMessage, setChatTyping, toggleChatReaction } from '../../api/chat';
import { useEcho } from '../../composables/useEcho';

const props = defineProps({
    poolId: { type: [String, Number], required: true },
    userId: { type: Number, default: 0 },
});

const messages     = ref([]);
const participants = ref([]);
const loading      = ref(true);
const sending      = ref(false);
const draft        = ref('');
const replyTo      = ref(null);
const typingUsers  = ref(new Map());
const readsMap     = ref({});
const listEl       = ref(null);
const chatRootEl   = ref(null);
const chatHeight   = ref(400);
const contextMessage   = ref(null);
const quickReactions   = ['👍', '❤️', '😂', '😮', '😡', '👏'];
const localMessageStates = ref({});
const swipeHintMessageId = ref(null);
const swipeOffsetMap     = ref({});
const atBottom           = ref(true);  // true = auto-scroll ativado

let echoChannel      = null;
let typingTimer      = null;
let resizeObserver   = null;
let bubbleTouchStartX  = 0;
let bubbleTouchStartY  = 0;
let longPressTimer     = null;
let activeTouchMessageId = null;

const canSend      = computed(() => draft.value.trim().length > 0);
const typingNames  = computed(() => Array.from(typingUsers.value.values()));
const otherParticipantIds = computed(() =>
    participants.value.map((p) => Number(p.id || 0)).filter((id) => id > 0 && id !== Number(props.userId || 0)),
);
const mentionSuggestions = computed(() => {
    const match = draft.value.match(/(?:^|\s)@([\p{L}0-9_\-.]{1,40})$/u);
    if (!match) return [];
    const term = String(match[1] || '').toLowerCase();
    if (!term) return [];
    return participants.value
        .filter((p) => Number(p.id) !== Number(props.userId || 0))
        .filter((p) => String(p.name || '').toLowerCase().includes(term))
        .slice(0, 6);
});

// ── Helpers ─────────────────────────────────────────────────────────────────

function initials(name) {
    const n = String(name || '').trim();
    return n ? n.split(/\s+/).slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('') : '?';
}
function hashString(text) {
    let h = 0;
    const s = String(text || '');
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h) + s.charCodeAt(i);
    return Math.abs(h);
}
function avatarBg(name)  { const p = ['#1d4ed8','#0f766e','#7c3aed','#b45309','#be123c','#0369a1']; return p[hashString(name) % p.length]; }
function nameColor(name) { const p = ['#60a5fa','#34d399','#a78bfa','#f59e0b','#f472b6','#22d3ee']; return p[hashString(name) % p.length]; }
function isMine(item)    { return Number(item.user?.id || 0) === Number(props.userId || 0); }

function allReadByOthers(messageId) {
    const id = Number(messageId || 0);
    if (!id) return false;
    const others = otherParticipantIds.value;
    if (!others.length) return false;
    return others.every((uid) => Number(readsMap.value?.[uid] || 0) >= id);
}

function swipeTranslateStyle(messageId) {
    const x = Number(swipeOffsetMap.value[String(messageId)] || 0);
    return x > 0 ? { transform: `translateX(${x}px)`, transition: 'none' } : null;
}

function formatLocalTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// ── Scroll ──────────────────────────────────────────────────────────────────

function onListScroll() {
    const el = listEl.value;
    if (!el) return;
    atBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
}

function scrollToBottom() {
    const el = listEl.value;
    if (!(el instanceof HTMLElement)) return;
    el.scrollTop = el.scrollHeight;
}

// Auto-scroll quando novas mensagens chegam e usuário está no fim
watch(() => messages.value.length, async () => {
    if (atBottom.value) {
        await nextTick();
        scrollToBottom();
    }
});

// ── Altura dinâmica ──────────────────────────────────────────────────────────
// Calcula o espaço disponível a partir do topo do componente até a tabbar.

function updateChatHeight() {
    const el = chatRootEl.value;
    if (!el) return;
    const rect        = el.getBoundingClientRect();
    const vvHeight    = window.visualViewport?.height ?? window.innerHeight;
    const tabbarH     = 68;
    const available   = vvHeight - rect.top - tabbarH;
    chatHeight.value  = Math.max(200, available);
}

// ── Data ─────────────────────────────────────────────────────────────────────

async function loadMessages() {
    const { data: res } = await getChatMessages(props.poolId, {});
    const items = Array.isArray(res?.data?.items) ? res.data.items : [];
    messages.value = items;
    if (res?.data?.reads_map && typeof res.data.reads_map === 'object') {
        readsMap.value = res.data.reads_map;
    }
    await nextTick();
    scrollToBottom();
    const lastId = messages.value.at(-1)?.id;
    if (lastId) await markChatRead(props.poolId, lastId).catch(() => {});
}

async function loadParticipants() {
    const { data: res } = await getChatParticipants(props.poolId);
    participants.value = Array.isArray(res?.data?.items) ? res.data.items : [];
}

// ── Actions ──────────────────────────────────────────────────────────────────

async function submit() {
    if (!canSend.value || sending.value) return;
    sending.value = true;
    try {
        const payload = { body: draft.value.trim() };
        if (replyTo.value?.id) payload.reply_to_message_id = replyTo.value.id;

        const tempId = `tmp_${Date.now()}`;
        messages.value.push({ id: tempId, body: payload.body, created_at: new Date().toISOString(),
            user: { id: Number(props.userId || 0), name: 'Você' },
            reply_to: replyTo.value ? { id: replyTo.value.id, body: replyTo.value.body, user_name: replyTo.value.user?.name } : null,
            reactions: [] });
        localMessageStates.value[String(tempId)] = 'pending';
        atBottom.value = true;
        await nextTick(); scrollToBottom();

        const { data: res } = await sendChatMessage(props.poolId, payload);
        const created = res?.data?.message;
        messages.value = messages.value.filter((m) => m.id !== tempId);
        delete localMessageStates.value[String(tempId)];
        if (created?.id) {
            messages.value.push(created);
            localMessageStates.value[String(created.id)] = 'sent';
        }
        await nextTick(); scrollToBottom();
        draft.value  = '';
        replyTo.value = null;
        await setChatTyping(props.poolId, false).catch(() => {});
    } catch (err) {
        console.error(err);
        messages.value = messages.value.filter((m) => !String(m.id).startsWith('tmp_'));
    } finally {
        sending.value = false;
    }
}

function startReply(item)  { replyTo.value = item; }

async function toggleReaction(messageId, emoji) {
    await toggleChatReaction(props.poolId, messageId, emoji).catch(() => {});
}

function applyMention(user) {
    draft.value = draft.value.replace(/(?:^|\s)@[\p{L}0-9_\-.]{1,40}$/u,
        (m) => `${m.startsWith(' ') ? ' ' : ''}@${user.name} `);
}

function onTypingInput() {
    setChatTyping(props.poolId, true).catch(() => {});
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => setChatTyping(props.poolId, false).catch(() => {}), 1500);
}

async function pickReaction(emoji) {
    if (contextMessage.value?.id) await toggleReaction(contextMessage.value.id, emoji);
    contextMessage.value = null;
}

async function copyMessage(msg) {
    if (msg?.body) await navigator.clipboard.writeText(msg.body).catch(() => {});
    contextMessage.value = null;
}

// ── Touch gestures ───────────────────────────────────────────────────────────

function onBubbleTouchStart(event, item) {
    const touch = event.touches?.[0];
    if (!touch) return;
    bubbleTouchStartX = touch.clientX;
    bubbleTouchStartY = touch.clientY;
    activeTouchMessageId = item?.id ?? null;
    if (longPressTimer) clearTimeout(longPressTimer);
    longPressTimer = setTimeout(() => { contextMessage.value = item; }, 420);
}

function onBubbleTouchMove(event) {
    const touch = event.touches?.[0];
    if (!touch) return;
    const dx = touch.clientX - bubbleTouchStartX;
    const dy = Math.abs(touch.clientY - bubbleTouchStartY);
    const key = String(activeTouchMessageId ?? '');
    if (dx > 0 && dy < 30 && key) swipeOffsetMap.value[key] = Math.min(42, dx * 0.35);
    if (Math.abs(dx) > 8 || dy > 8) { if (longPressTimer) clearTimeout(longPressTimer); }
}

function onBubbleTouchEnd(event, item) {
    if (longPressTimer) clearTimeout(longPressTimer);
    const touch = event.changedTouches?.[0];
    if (!touch) return;
    const dx = touch.clientX - bubbleTouchStartX;
    const dy = Math.abs(touch.clientY - bubbleTouchStartY);
    swipeHintMessageId.value = null;
    swipeOffsetMap.value[String(item.id)] = 0;
    activeTouchMessageId = null;
    if (dx > 30 && dy < 30) {
        swipeHintMessageId.value = item.id;
        setTimeout(() => { swipeHintMessageId.value = null; }, 900);
        startReply(item);
    }
}

// ── Realtime ─────────────────────────────────────────────────────────────────

function bindRealtime() {
    const echo = useEcho();
    if (!echo) return;
    echoChannel = echo.private(`pool-chat.${props.poolId}`)
        .listen('.PoolChatMessageCreated', async (payload) => {
            const msg = payload?.message;
            if (!msg) return;
            if (!messages.value.some((m) => Number(m.id) === Number(msg.id))) messages.value.push(msg);
            localMessageStates.value[String(msg.id)] = 'sent';
            if (msg.id) await markChatRead(props.poolId, msg.id).catch(() => {});
        })
        .listen('.PoolChatReactionChanged', (payload) => {
            const id  = Number(payload?.message_id || 0);
            const idx = messages.value.findIndex((m) => Number(m.id) === id);
            if (idx >= 0) messages.value[idx] = { ...messages.value[idx], reactions: Array.isArray(payload?.reactions) ? payload.reactions : [] };
        })
        .listen('.PoolChatTypingChanged', (payload) => {
            const uid = Number(payload?.user_id || 0);
            if (!uid || uid === Number(props.userId || 0)) return;
            if (payload?.typing) {
                typingUsers.value.set(uid, payload?.user_name || 'Alguém');
                setTimeout(() => { typingUsers.value.delete(uid); typingUsers.value = new Map(typingUsers.value); }, 5000);
            } else { typingUsers.value.delete(uid); }
            typingUsers.value = new Map(typingUsers.value);
        })
        .listen('.PoolChatReadUpdated', (payload) => {
            const uid  = Number(payload?.user_id || 0);
            const last = Number(payload?.last_read_message_id || 0);
            if (uid && last) readsMap.value = { ...readsMap.value, [uid]: last };
        });

    // Evita loop ruidoso no console quando backend nega autorização do canal.
    if (typeof echoChannel?.error === 'function') {
        echoChannel.error(() => {});
    }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
    // Calcula altura inicial após DOM estar disponível
    await nextTick();
    updateChatHeight();

    // Re-calcula quando o teclado abre/fecha (visualViewport) ou janela redimensiona
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateChatHeight);
        window.visualViewport.addEventListener('scroll', updateChatHeight);
    }
    window.addEventListener('resize', updateChatHeight);

    // Observa mudanças no tamanho do próprio elemento (ex: pool header colapsa)
    if (window.ResizeObserver) {
        resizeObserver = new ResizeObserver(updateChatHeight);
        if (chatRootEl.value?.parentElement) resizeObserver.observe(chatRootEl.value.parentElement);
    }

    try {
        await Promise.all([loadMessages(), loadParticipants()]);
        bindRealtime();
    } finally {
        loading.value = false;
        await nextTick();
        scrollToBottom();
    }
});

onBeforeUnmount(() => {
    if (typingTimer) clearTimeout(typingTimer);
    if (longPressTimer) clearTimeout(longPressTimer);
    setChatTyping(props.poolId, false).catch(() => {});
    if (window.visualViewport) {
        window.visualViewport.removeEventListener('resize', updateChatHeight);
        window.visualViewport.removeEventListener('scroll', updateChatHeight);
    }
    window.removeEventListener('resize', updateChatHeight);
    resizeObserver?.disconnect();
    if (echoChannel) { const echo = useEcho(); echo?.leave(`pool-chat.${props.poolId}`); }
});
</script>

<style scoped>
/* ── Layout root ────────────────────────────────────────── */
.chat-root {
    display: flex;
    flex-direction: column;
    background: #0b141a;
    overflow: hidden;
    /* Largura ocupa o pai; altura vem do JS */
    width: 100%;
}

/* ── Messages scroll ────────────────────────────────────── */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    padding: 10px 12px 4px;
    min-height: 0;   /* critical para flex scrolling */
}

/* ── Bubbles ────────────────────────────────────────────── */
.chat-bubble-wrap {
    position: relative;
    max-width: 82%;
    border-radius: 18px;
    padding: 7px 10px 5px;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
    word-break: break-word;
    overflow-wrap: break-word;
}
.chat-bubble-wrap-me {
    background: linear-gradient(160deg, #123f3a, #0e3430);
    border: 1px solid rgba(74,222,128,.18);
    color: #ecfeff;
    border-radius: 16px 16px 4px 16px;
}
.chat-bubble-wrap-other {
    background: linear-gradient(160deg, #1a2433, #141e2b);
    border: 1px solid rgba(148,163,184,.14);
    color: #e2e8f0;
    border-radius: 16px 16px 16px 4px;
}
.chat-bubble-wrap:active { opacity: .9; }

.chat-author {
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 3px;
    display: block;
}

.chat-body {
    font-size: 14px;
    line-height: 1.4;
    white-space: pre-wrap;
    display: inline;
}

/* Espaçador invisível antes dos metadados para reservar espaço */
.chat-meta-spacer {
    display: inline-block;
    width: 56px;   /* aprox largura de "23:59 ✓✓" */
}

.chat-meta-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
    margin-top: 2px;
}

.chat-time {
    font-size: 10px;
    opacity: .55;
}

.chat-tick       { line-height: 1; }
.chat-tick-sent  { opacity: .6; }
.chat-tick-read  { color: #60a5fa; }

.chat-avatar {
    width: 28px; height: 28px;
    border-radius: 999px;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-bottom: 2px;
    align-self: flex-end;
}

/* Reply quote */
.chat-reply {
    border-radius: 8px;
    border-left: 3px solid rgba(245,166,35,.8);
    background: rgba(0,0,0,.2);
}

/* Reactions */
.chat-reaction-float {
    position: absolute;
    bottom: -14px;
    left: 8px;
    min-height: 22px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,.15);
    background: #111b25;
    padding: 1px 7px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 13px;
    box-shadow: 0 4px 12px rgba(0,0,0,.4);
    z-index: 2;
}
.chat-reaction-count { font-size: 10px; color: rgba(226,232,240,.8); font-weight: 700; }

/* ── Typing / Mentions / Reply bars ──────────────────────── */
.typing-bar {
    flex-shrink: 0;
    font-size: 11px;
    color: #64748b;
    padding: 4px 14px 2px;
}
.mention-bar {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px 12px;
    border-top: 1px solid rgba(255,255,255,.06);
    background: #0f172a;
}
.reply-preview {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-top: 1px solid rgba(255,255,255,.06);
    background: #141d27;
}

/* ── Composer (NÃO é fixed) ─────────────────────────────── */
.chat-composer {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #111b25;
    border-top: 1px solid rgba(255,255,255,.07);
}

.chat-input {
    flex: 1;
    min-height: 40px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,.14);
    background: #1a2535;
    color: #e5e7eb;
    padding: 0 14px;
    font-size: 14px;
    outline: none;
}
.chat-input::placeholder { color: #8fa0b8; }
.chat-input:focus {
    border-color: rgba(245,166,35,.5);
    box-shadow: 0 0 0 2px rgba(245,166,35,.12);
}

.chat-send-btn {
    width: 40px; height: 40px;
    border-radius: 999px;
    border: none;
    background: rgba(245,166,35,.9);
    color: #10131a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform .1s, opacity .1s;
}
.chat-send-btn:active  { transform: scale(.93); }
.chat-send-btn:disabled { opacity: .4; }
</style>

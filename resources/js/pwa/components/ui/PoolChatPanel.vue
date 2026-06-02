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

            <template v-for="(item, idx) in messages" :key="item.id">
                <div v-if="showDateDivider(idx)" class="chat-date-divider">
                    <span>{{ formatDateLabel(item.created_at) }}</span>
                </div>

                <div class="flex items-end gap-2 mb-1"
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
                            <p class="text-[12px] text-white/80 line-clamp-2">{{ item.reply_to.body }}</p>
                        </div>

                        <!-- Texto + hora na mesma linha ao fim -->
                        <span class="chat-body" :class="{ 'chat-body-deleted': !!item.deleted_at }">{{ item.body }}<span class="chat-meta-spacer">&#8203;&#xFEFF;</span></span>
                        <div class="chat-meta-row">
                            <span v-if="item.edited_at" class="chat-edited-label">editada</span>
                            <span class="chat-time">{{ formatLocalTime(item.created_at) }}</span>
                            <span v-if="isMine(item)"
                                  class="chat-tick"
                                  :class="allReadByOthers(item.id) ? 'chat-tick-read' : 'chat-tick-sent'">
                                <i class="ti ti-checks text-[11px]"></i>
                            </span>
                        </div>

                        <!-- Reactions -->
                        <div v-if="item.reactions?.length" class="chat-reactions-row">
                            <button
                                v-for="reaction in item.reactions"
                                :key="reaction.emoji"
                                class="chat-reaction-plain"
                                :class="{ active: isReactionMine(reaction) }"
                                @click="toggleReaction(item.id, reaction.emoji)"
                            >
                                <span class="chat-reaction-emoji">{{ reaction.emoji }}</span>
                                <span class="chat-reaction-count">{{ Number(reaction.count || 0) }}</span>
                            </button>
                        </div>
                        <div v-if="item.audit?.edits?.length || item.audit?.deleted_body" class="chat-audit">
                            <p v-if="item.audit?.deleted_body" class="chat-audit-line">Apagada: {{ item.audit.deleted_body }}</p>
                            <p v-for="(edit, editIdx) in item.audit?.edits || []" :key="editIdx" class="chat-audit-line">
                                Editada: "{{ edit.old_body }}" -> "{{ edit.new_body }}"
                            </p>
                        </div>
                    </div>
                </div>
            </template>

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
                    <button class="text-xs text-slate-300 py-1" :disabled="!!contextMessage?.deleted_at" @click.stop="startReply(contextMessage); contextMessage = null">Responder</button>
                    <button class="text-xs text-slate-300 py-1" @click.stop="copyMessage(contextMessage)">Copiar</button>
                    <button v-if="canEditMessage(contextMessage)" class="text-xs text-slate-300 py-1" @click.stop="startEdit(contextMessage); contextMessage = null">Editar</button>
                    <button v-if="isMine(contextMessage) && !contextMessage?.deleted_at" class="text-xs text-red-300 py-1" @click.stop="removeMessage(contextMessage); contextMessage = null">Excluir</button>
                </div>
            </div>
        </div>

        <!-- ── Typing indicator ── -->
        <div v-if="typingNames.length" class="typing-bar">
            <span>{{ typingLabel }}</span>
            <span class="typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>

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
        <div v-if="editingMessageId" class="reply-preview">
            <div class="flex-1 min-w-0 border-l-2 border-emerald-400 pl-2">
                <p class="text-[11px] font-bold text-emerald-300">Editando mensagem</p>
            </div>
            <button class="shrink-0 text-slate-400 p-1" @click="cancelEdit">
                <i class="ti ti-x text-[15px]"></i>
            </button>
        </div>

        <!-- ── Composer (NÃO é fixed — parte do flex column) ── -->
        <div class="chat-composer">
            <input v-model="draft"
                   class="chat-input flex-1"
                   maxlength="2000"
                   :placeholder="editingMessageId ? 'Edite sua mensagem' : 'Digite uma mensagem'"
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
import { deleteChatMessage, getChatMessages, getChatParticipants, markChatRead, sendChatMessage, setChatTyping, toggleChatReaction, updateChatMessage } from '../../api/chat';
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
const editingMessageId = ref(null);
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
const recentSubmitFingerprint = ref({ body: '', at: 0 });

let echoChannel      = null;
let typingTimer      = null;
let resizeObserver   = null;
let bubbleTouchStartX  = 0;
let bubbleTouchStartY  = 0;
let longPressTimer     = null;
let activeTouchMessageId = null;
const typingExpiresAt = new Map();

const canSend      = computed(() => draft.value.trim().length > 0);
const typingNames  = computed(() => Array.from(typingUsers.value.values()));
const typingLabel  = computed(() => {
    const names = typingNames.value;
    if (names.length === 1) return `${names[0]} está digitando`;
    if (names.length === 2) return `${names[0]} e ${names[1]} estão digitando`;
    return `${names.length} pessoas estão digitando`;
});
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
function isReactionMine(reaction) {
    return (reaction?.user_ids || []).map((id) => Number(id)).includes(Number(props.userId || 0));
}
function canEditMessage(item) {
    return !!item && isMine(item) && !item.deleted_at && item.can_edit !== false;
}

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

function showDateDivider(index) {
    if (index === 0) return true;
    const current = messages.value[index]?.created_at;
    const previous = messages.value[index - 1]?.created_at;
    if (!current || !previous) return false;
    return dateKey(current) !== dateKey(previous);
}

function dateKey(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function formatDateLabel(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const today = new Date();
    if (date.toDateString() === today.toDateString()) return 'Hoje';
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) return 'Ontem';
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
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

function upsertMessage(message) {
    const id = Number(message?.id || 0);
    const incomingBody = String(message?.body ?? '').trim();
    const incomingUserId = Number(message?.user?.id || 0);
    const incomingCreatedAt = message?.created_at ? new Date(message.created_at).getTime() : 0;
    if (!id && !incomingBody) return;

    // 1) Dedupe por ID (caso padrão)
    const idx = id
        ? messages.value.findIndex((m) => Number(m?.id || 0) === id)
        : -1;
    if (idx >= 0) {
        messages.value[idx] = { ...messages.value[idx], ...message };
        return;
    }

    // 2) Dedupe por assinatura (mesmo usuário + mesmo texto + janela curta)
    // Protege quando API/Echo retornam entidades equivalentes com IDs distintos.
    const sigIdx = messages.value.findIndex((m) => {
        const body = String(m?.body ?? '').trim();
        const userId = Number(m?.user?.id || 0);
        const createdAt = m?.created_at ? new Date(m.created_at).getTime() : 0;
        if (!incomingBody || !incomingUserId || !incomingCreatedAt || !createdAt) return false;
        if (body !== incomingBody || userId !== incomingUserId) return false;
        return Math.abs(createdAt - incomingCreatedAt) <= 4000;
    });

    if (sigIdx >= 0) {
        messages.value[sigIdx] = { ...messages.value[sigIdx], ...message };
        return;
    }

    messages.value.push(message);
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
    const vv          = window.visualViewport;
    const viewportH   = vv?.height ?? window.innerHeight;
    const viewportTop = vv?.offsetTop ?? 0;
    const keyboardOpen = !!vv && (window.innerHeight - vv.height) > 120;

    const tabbarEl = document.querySelector('.pwa-tabbar');
    let tabbarH = 0;
    if (tabbarEl instanceof HTMLElement && !keyboardOpen) {
        const tabRect = tabbarEl.getBoundingClientRect();
        if (tabRect.height > 0 && tabRect.top < (viewportTop + viewportH)) {
            tabbarH = tabRect.height;
        }
    }

    const available = (viewportTop + viewportH) - rect.top - tabbarH;
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
    const body = draft.value.trim();
    const nowTs = Date.now();
    if (
        recentSubmitFingerprint.value.body === body &&
        (nowTs - Number(recentSubmitFingerprint.value.at || 0)) < 1200
    ) {
        return;
    }

    sending.value = true;
    let tempId = null;
    try {
        const payload = { body };
        if (replyTo.value?.id && !editingMessageId.value) payload.reply_to_message_id = replyTo.value.id;
        recentSubmitFingerprint.value = { body, at: nowTs };

        if (editingMessageId.value) {
            const { data: res } = await updateChatMessage(props.poolId, editingMessageId.value, payload);
            const updated = res?.data?.message;
            if (updated?.id) upsertMessage(updated);
            draft.value = '';
            editingMessageId.value = null;
            replyTo.value = null;
            return;
        }

        tempId = `tmp_${Date.now()}`;
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
            upsertMessage(created);
            localMessageStates.value[String(created.id)] = 'sent';
        }
        await nextTick(); scrollToBottom();
        draft.value  = '';
        replyTo.value = null;
        await setChatTyping(props.poolId, false).catch(() => {});
    } catch (err) {
        console.error(err);
        if (tempId) {
            messages.value = messages.value.filter((m) => m.id !== tempId);
            delete localMessageStates.value[String(tempId)];
        }
    } finally {
        sending.value = false;
    }
}

function startReply(item)  { replyTo.value = item; }

async function toggleReaction(messageId, emoji) {
    const target = messages.value.find((m) => Number(m.id) === Number(messageId));
    if (target?.deleted_at) return;
    await toggleChatReaction(props.poolId, messageId, emoji).catch(() => {});
}

function startEdit(item) {
    if (!canEditMessage(item)) return;
    editingMessageId.value = Number(item.id || 0) || null;
    draft.value = String(item.body || '');
    replyTo.value = null;
}

function cancelEdit() {
    editingMessageId.value = null;
    draft.value = '';
}

async function removeMessage(item) {
    if (!item || !isMine(item) || item.deleted_at) return;
    await deleteChatMessage(props.poolId, item.id).catch(() => {});
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
    if (item?.deleted_at) return;
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
    if (item?.deleted_at) return;
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
            upsertMessage(msg);
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
                const expiresAt = Date.now() + 5000;
                typingExpiresAt.set(uid, expiresAt);
                typingUsers.value.set(uid, payload?.user_name || 'Alguém');
                setTimeout(() => {
                    if (typingExpiresAt.get(uid) !== expiresAt) return;
                    typingExpiresAt.delete(uid);
                    typingUsers.value.delete(uid);
                    typingUsers.value = new Map(typingUsers.value);
                }, 5000);
            } else {
                typingExpiresAt.delete(uid);
                typingUsers.value.delete(uid);
            }
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

.chat-date-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 12px 0;
}

.chat-date-divider span {
    border-radius: 999px;
    background: rgba(17,27,37,.92);
    color: #9fb0c9;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.22);
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
.chat-body-deleted {
    font-style: italic;
    opacity: .75;
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

.chat-edited-label {
    font-size: 10px;
    opacity: .5;
    font-style: italic;
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
.chat-reactions-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin-top: 5px;
}

.chat-reaction-plain {
    display: inline-flex;
    align-items: baseline;
    gap: 2px;
    border: 0;
    background: transparent;
    color: rgba(233,237,239,.82);
    padding: 0;
    line-height: 1;
}

.chat-reaction-plain.active {
    color: #7dd3fc;
}

.chat-reaction-emoji {
    font-size: 15px;
    line-height: 1;
}

.chat-reaction-count {
    font-size: 10px;
    font-weight: 800;
    color: currentColor;
}

.chat-audit {
    margin-top: 6px;
    border-top: 1px solid rgba(255,255,255,.08);
    padding-top: 5px;
}

.chat-audit-line {
    color: #fbbf24;
    font-size: 10px;
    line-height: 1.35;
    opacity: .9;
}

/* ── Typing / Mentions / Reply bars ──────────────────────── */
.typing-bar {
    flex-shrink: 0;
    font-size: 11px;
    color: #64748b;
    padding: 4px 14px 2px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    transform: translateY(1px);
}

.typing-dots i {
    width: 3px;
    height: 3px;
    border-radius: 999px;
    background: currentColor;
    animation: chatTypingDot 1.05s infinite ease-in-out;
}

.typing-dots i:nth-child(2) { animation-delay: .16s; }
.typing-dots i:nth-child(3) { animation-delay: .32s; }

@keyframes chatTypingDot {
    0%, 70%, 100% { opacity: .35; transform: translateY(0); }
    35% { opacity: 1; transform: translateY(-3px); }
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

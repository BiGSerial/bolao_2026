<template>
    <div class="pwa-section pt-3 pb-28 space-y-3">
        <div class="-mx-5">
        <div class="chat-thread max-h-[58vh] overflow-y-auto px-3 py-2" ref="listEl">
            <div class="space-y-3">
                <div v-for="item in messages" :key="item.id" class="flex items-end gap-2" :class="isMine(item) ? 'justify-end' : 'justify-start'">
                    <div v-if="!isMine(item)" class="chat-avatar" :style="{ background: avatarBg(item.user?.name) }">
                        {{ initials(item.user?.name) }}
                    </div>
                    <div
                        v-if="swipeHintMessageId === item.id"
                        class="text-[11px] text-bolao-accent flex items-center gap-1"
                    >
                        <i class="ti ti-arrow-back-up"></i><span>Responder</span>
                    </div>
                    <div
                        class="chat-bubble-wrap relative max-w-[88%]"
                        :class="isMine(item) ? 'pr-1' : 'pl-1'"
                    >
                    <div
                        class="chat-bubble rounded-2xl px-3 py-2"
                        :class="isMine(item) ? 'chat-bubble-me' : 'chat-bubble-other'"
                        :style="swipeTranslateStyle(item.id)"
                        @dblclick="toggleReaction(item.id, '👍')"
                        @touchstart="onBubbleTouchStart($event, item)"
                        @touchmove="onBubbleTouchMove($event)"
                        @touchend="onBubbleTouchEnd($event, item)"
                    >
                        <p v-if="!isMine(item)" class="chat-author" :style="{ color: nameColor(item.user?.name) }">{{ item.user?.name || '—' }}</p>
                        <div v-if="item.reply_to" class="chat-reply px-2 py-1 mb-1.5">
                            <p class="text-[10px] text-slate-300/90">Respondendo {{ item.reply_to.user_name }}</p>
                            <p class="text-[11px] text-white/90 truncate">{{ item.reply_to.body }}</p>
                        </div>
                        <p class="chat-body">{{ item.body }}</p>
                        <div class="mt-1 flex items-end justify-end gap-2">
                            <div class="flex items-center gap-2">
                                <p class="chat-meta">{{ formatLocalTime(item.created_at) }}</p>
                            </div>
                            <span v-if="isMine(item)" class="chat-tick-wrap" :class="allReadByOthers(item.id) ? 'chat-tick-read' : 'chat-tick-sent'">
                                <i class="ti ti-checks text-[12px]"></i>
                            </span>
                        </div>
                    </div>
                    <button
                        v-if="item.reactions?.length"
                        class="chat-reaction-float"
                        :class="isMine(item) ? 'chat-reaction-float-me' : 'chat-reaction-float-other'"
                        @click="toggleReaction(item.id, item.reactions[0].emoji)"
                    >
                        <span class="leading-none">{{ item.reactions[0].emoji }}</span>
                        <span v-if="(item.reactions[0].count || 0) > 1" class="chat-reaction-count">{{ item.reactions[0].count }}</span>
                    </button>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <p v-if="typingNames.length" class="text-[11px] text-bolao-muted px-1">{{ typingNames.join(', ') }} digitando...</p>

        <div v-if="mentionSuggestions.length" class="pwa-card p-2 border border-white/10">
            <p class="text-[10px] text-bolao-muted2 mb-1">Mencionar</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="user in mentionSuggestions"
                    :key="user.id"
                    class="text-[11px] rounded-full border border-white/15 px-2 py-0.5 text-slate-200"
                    @click="applyMention(user)"
                >
                    @{{ user.name }}
                </button>
            </div>
        </div>

        <div
            v-if="contextMessage"
            class="fixed inset-0 z-40 bg-black/30"
            @click="contextMessage = null"
        >
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-white/15 bg-[#0f172a] px-3 py-2 shadow-xl">
                <div class="flex items-center gap-2">
                    <button v-for="emoji in quickReactions" :key="emoji" class="text-xl" @click.stop="pickReaction(emoji)">{{ emoji }}</button>
                </div>
                <div class="mt-2 border-t border-white/10 pt-2 grid grid-cols-2 gap-2 text-center">
                    <button class="text-xs text-slate-300" @click.stop="copyMessage(contextMessage)">Copiar</button>
                    <button class="text-xs text-slate-300" @click.stop="contextMessage = null">Fechar</button>
                </div>
            </div>
        </div>

        <div class="chat-composer fixed left-0 right-0 px-3 py-2 bg-[#0b0f16]/96 backdrop-blur border-t border-white/10" :style="composerStyle">
            <div class="max-w-[520px] mx-auto">
                <div v-if="replyTo" class="chat-reply-composer mb-2">
                    <div class="min-w-0">
                        <p class="chat-reply-composer-name">{{ replyTo.user?.name }}</p>
                        <p class="chat-reply-composer-body">{{ replyTo.body }}</p>
                    </div>
                    <button class="chat-reply-composer-close" @click="replyTo = null" aria-label="Cancelar resposta">
                        <i class="ti ti-x text-[16px]"></i>
                    </button>
                </div>
                <div class="flex items-end gap-2">
                <input
                    v-model="draft"
                    class="chat-input flex-1"
                    maxlength="2000"
                    placeholder="Digite uma mensagem"
                    @input="onTypingInput"
                    @keydown.enter.prevent="submit"
                >
                <button
                    class="chat-send-btn"
                    :disabled="sending || !canSend"
                    @click="submit"
                    aria-label="Enviar mensagem"
                >
                    <i class="ti ti-send text-[16px]"></i>
                </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { getChatMessages, getChatParticipants, markChatRead, sendChatMessage, setChatTyping, toggleChatReaction } from '../../api/chat';
import { useEcho } from '../../composables/useEcho';

const props = defineProps({
    poolId: { type: [String, Number], required: true },
    userId: { type: Number, default: 0 },
});

const messages = ref([]);
const participants = ref([]);
const loading = ref(true);
const sending = ref(false);
const draft = ref('');
const replyTo = ref(null);
const typingUsers = ref(new Map());
const readsMap = ref({});
const listEl = ref(null);
const keyboardInset = ref(68);
const contextMessage = ref(null);
const quickReactions = ['👍', '❤️', '😂', '😮', '😡', '👏'];
const localMessageStates = ref({});
const swipeHintMessageId = ref(null);
const swipeOffsetMap = ref({});
let echoChannel = null;
let typingTimer = null;
let viewportListener = null;
let bubbleTouchStartX = 0;
let bubbleTouchStartY = 0;
let longPressTimer = null;
let activeTouchMessageId = null;

const canSend = computed(() => draft.value.trim().length > 0);
const typingNames = computed(() => Array.from(typingUsers.value.values()));
const composerStyle = computed(() => ({ bottom: `${keyboardInset.value}px` }));
const otherParticipantIds = computed(() =>
    participants.value
        .map((p) => Number(p.id || 0))
        .filter((id) => id > 0 && id !== Number(props.userId || 0)),
);
const mentionSuggestions = computed(() => {
    const text = draft.value;
    const match = text.match(/(?:^|\s)@([\p{L}0-9_\-.]{1,40})$/u);
    if (!match) return [];
    const term = String(match[1] || '').toLowerCase();
    if (!term) return [];
    return participants.value
        .filter((p) => Number(p.id) !== Number(props.userId || 0))
        .filter((p) => String(p.name || '').toLowerCase().includes(term))
        .slice(0, 6);
});

function initials(name) {
    const n = String(name || '').trim();
    if (!n) return '?';
    const parts = n.split(/\s+/).slice(0, 2);
    return parts.map((p) => p.charAt(0).toUpperCase()).join('');
}

function hashString(text) {
    let h = 0;
    const s = String(text || '');
    for (let i = 0; i < s.length; i += 1) h = ((h << 5) - h) + s.charCodeAt(i);
    return Math.abs(h);
}

function avatarBg(name) {
    const palette = ['#1d4ed8', '#0f766e', '#7c3aed', '#b45309', '#be123c', '#0369a1'];
    return palette[hashString(name) % palette.length];
}

function nameColor(name) {
    const palette = ['#60a5fa', '#34d399', '#a78bfa', '#f59e0b', '#f472b6', '#22d3ee'];
    return palette[hashString(name) % palette.length];
}

function isMine(item) {
    return Number(item.user?.id || 0) === Number(props.userId || 0);
}

function readState(messageId) {
    const ownId = Number(props.userId || 0);
    const readValues = Object.entries(readsMap.value || {})
        .filter(([uid]) => Number(uid) !== ownId)
        .map(([, last]) => Number(last || 0));
    const anyRead = readValues.some((last) => last >= Number(messageId || 0));
    return anyRead ? 'Lido' : 'Enviado';
}

function messageState(messageId) {
    const local = localMessageStates.value[String(messageId)];
    if (local === 'pending') return 'Enviando...';
    return readState(messageId);
}

function messageStateClass(messageId) {
    const local = localMessageStates.value[String(messageId)];
    if (local === 'pending') return 'text-slate-400';
    return readState(messageId) === 'Lido' ? 'text-emerald-300' : 'text-slate-500';
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
    return x > 0 ? { transform: `translateX(${x}px)` } : null;
}

function formatLocalTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

async function loadMessages(beforeId = null) {
    const { data: res } = await getChatMessages(props.poolId, beforeId ? { before_id: beforeId } : {});
    const items = Array.isArray(res?.data?.items) ? res.data.items : [];
    if (!beforeId) {
        messages.value = items;
    } else {
        messages.value = [...items, ...messages.value];
    }

    if (res?.data?.reads_map && typeof res.data.reads_map === 'object') {
        readsMap.value = res.data.reads_map;
    }

    await nextTick();
    scrollToBottom();
    const lastId = messages.value.at(-1)?.id;
    if (lastId) {
        await markChatRead(props.poolId, lastId);
    }
}

async function loadParticipants() {
    const { data: res } = await getChatParticipants(props.poolId);
    participants.value = Array.isArray(res?.data?.items) ? res.data.items : [];
}

function scrollToBottom() {
    const el = listEl.value;
    if (!(el instanceof HTMLElement)) return;
    el.scrollTop = el.scrollHeight;
}

async function submit() {
    if (!canSend.value || sending.value) return;
    sending.value = true;
    try {
        const payload = { body: draft.value.trim() };
        if (replyTo.value?.id) payload.reply_to_message_id = replyTo.value.id;
        const tempId = `tmp_${Date.now()}`;
        messages.value.push({
            id: tempId,
            body: payload.body,
            created_at: new Date().toISOString(),
            user: { id: Number(props.userId || 0), name: 'Você' },
            reply_to: replyTo.value ? { id: replyTo.value.id, body: replyTo.value.body, user_name: replyTo.value.user?.name } : null,
            reactions: [],
        });
        localMessageStates.value[String(tempId)] = 'pending';
        await nextTick();
        scrollToBottom();

        const { data: res } = await sendChatMessage(props.poolId, payload);
        const created = res?.data?.message;
        messages.value = messages.value.filter((m) => m.id !== tempId);
        delete localMessageStates.value[String(tempId)];
        if (created?.id) {
            messages.value.push(created);
            localMessageStates.value[String(created.id)] = 'sent';
        }
        await nextTick();
        scrollToBottom();
        draft.value = '';
        replyTo.value = null;
        await setChatTyping(props.poolId, false);
    } catch (err) {
        console.error(err);
        messages.value = messages.value.filter((m) => !String(m.id).startsWith('tmp_'));
    } finally {
        sending.value = false;
    }
}

function startReply(item) {
    replyTo.value = item;
}

async function toggleReaction(messageId, emoji) {
    await toggleChatReaction(props.poolId, messageId, emoji);
}

function applyMention(user) {
    const mention = `@${user.name}`;
    draft.value = draft.value.replace(/(?:^|\s)@[\p{L}0-9_\-.]{1,40}$/u, (m) => `${m.startsWith(' ') ? ' ' : ''}${mention} `);
}

function onBubbleTouchStart(event, item) {
    const touch = event.touches?.[0];
    if (!touch) return;
    bubbleTouchStartX = touch.clientX;
    bubbleTouchStartY = touch.clientY;
    activeTouchMessageId = item?.id ?? null;
    if (longPressTimer) clearTimeout(longPressTimer);
    longPressTimer = setTimeout(() => {
        contextMessage.value = item;
    }, 420);
}

function onBubbleTouchMove(event) {
    const touch = event.touches?.[0];
    if (!touch) return;
    const dx = Math.abs(touch.clientX - bubbleTouchStartX);
    const dy = Math.abs(touch.clientY - bubbleTouchStartY);
    const realDx = touch.clientX - bubbleTouchStartX;
    const key = String(activeTouchMessageId ?? '');
    if (realDx > 0 && dy < 30 && key) {
        swipeOffsetMap.value[key] = Math.min(42, realDx * 0.35);
    }
    if (dx > 8 || dy > 8) {
        if (longPressTimer) clearTimeout(longPressTimer);
    }
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

async function pickReaction(emoji) {
    const msg = contextMessage.value;
    if (!msg?.id) {
        contextMessage.value = null;
        return;
    }
    await toggleReaction(msg.id, emoji);
    contextMessage.value = null;
}

async function copyMessage(msg) {
    if (!msg?.body) return;
    try {
        await navigator.clipboard.writeText(msg.body);
    } catch (e) {
        console.error(e);
    } finally {
        contextMessage.value = null;
    }
}

function onTypingInput() {
    setChatTyping(props.poolId, true).catch(() => {});
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        setChatTyping(props.poolId, false).catch(() => {});
    }, 1500);
}

function bindRealtime() {
    const echo = useEcho();
    if (!echo) return;

    echoChannel = echo.private(`pool-chat.${props.poolId}`)
        .listen('.PoolChatMessageCreated', async (payload) => {
            const msg = payload?.message;
            if (!msg) return;
            if (!messages.value.some((m) => Number(m.id) === Number(msg.id))) {
                messages.value.push(msg);
            }
            localMessageStates.value[String(msg.id)] = 'sent';
            await nextTick();
            scrollToBottom();
            if (msg.id) {
                await markChatRead(props.poolId, msg.id);
            }
        })
        .listen('.PoolChatReactionChanged', (payload) => {
            const id = Number(payload?.message_id || 0);
            const idx = messages.value.findIndex((m) => Number(m.id) === id);
            if (idx >= 0) {
                messages.value[idx] = {
                    ...messages.value[idx],
                    reactions: Array.isArray(payload?.reactions) ? payload.reactions : [],
                };
            }
        })
        .listen('.PoolChatTypingChanged', (payload) => {
            const userId = Number(payload?.user_id || 0);
            if (!userId || userId === Number(props.userId || 0)) return;

            if (payload?.typing) {
                typingUsers.value.set(userId, payload?.user_name || 'Alguém');
                setTimeout(() => {
                    typingUsers.value.delete(userId);
                }, 5000);
            } else {
                typingUsers.value.delete(userId);
            }
            typingUsers.value = new Map(typingUsers.value);
        })
        .listen('.PoolChatReadUpdated', (payload) => {
            const uid = Number(payload?.user_id || 0);
            const last = Number(payload?.last_read_message_id || 0);
            if (!uid || !last) return;
            readsMap.value = { ...readsMap.value, [uid]: last };
            for (const message of messages.value) {
                if (Number(message.user?.id || 0) === Number(props.userId || 0) && Number(message.id) <= last) {
                    localMessageStates.value[String(message.id)] = 'read';
                }
            }
        });
}

onMounted(async () => {
    if (window.visualViewport) {
        const updateViewportInset = () => {
            const vv = window.visualViewport;
            const viewportHeight = vv?.height ?? window.innerHeight;
            const keyboardHeight = Math.max(0, window.innerHeight - viewportHeight - (vv?.offsetTop ?? 0));
            keyboardInset.value = keyboardHeight > 0 ? keyboardHeight + 8 : 68;
        };
        viewportListener = updateViewportInset;
        window.visualViewport.addEventListener('resize', updateViewportInset);
        window.visualViewport.addEventListener('scroll', updateViewportInset);
        updateViewportInset();
    }

    try {
        await Promise.all([loadMessages(), loadParticipants()]);
        bindRealtime();
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    if (typingTimer) clearTimeout(typingTimer);
    if (longPressTimer) clearTimeout(longPressTimer);
    setChatTyping(props.poolId, false).catch(() => {});
    if (window.visualViewport && viewportListener) {
        window.visualViewport.removeEventListener('resize', viewportListener);
        window.visualViewport.removeEventListener('scroll', viewportListener);
    }
    if (echoChannel) {
        const echo = useEcho();
        echo?.leave(`pool-chat.${props.poolId}`);
    }
});
</script>

<style scoped>
.chat-composer {
    z-index: 35;
}

.chat-thread {
    border-radius: 0;
    border: 0;
    min-height: 58vh;
    background: transparent;
}

.chat-bubble {
    transition: transform .14s ease, background-color .14s ease, box-shadow .14s ease;
    box-shadow: 0 4px 14px rgba(2, 6, 23, 0.28);
}

.chat-bubble:active {
    transform: scale(0.995);
}

.chat-bubble-me {
    background: linear-gradient(180deg, #123f3a 0%, #113632 100%);
    border: 1px solid rgba(74, 222, 128, 0.22);
    color: #ecfeff;
}

.chat-bubble-other {
    background: linear-gradient(180deg, #1a2433 0%, #15202f 100%);
    border: 1px solid rgba(148, 163, 184, 0.18);
    color: #e2e8f0;
}

.chat-bubble-wrap {
    display: inline-flex;
    flex-direction: column;
    position: relative;
    margin-bottom: 8px;
}

.chat-avatar {
    width: 30px;
    height: 30px;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
    flex: 0 0 30px;
}

.chat-author {
    font-size: 12px;
    margin-bottom: 4px;
    font-weight: 800;
}

.chat-body {
    font-size: 14px;
    line-height: 1.35;
    color: #f8fafc;
    white-space: pre-wrap;
    word-break: break-word;
}

.chat-meta {
    font-size: 10px;
    color: rgba(226, 232, 240, 0.58);
}

.chat-tick-wrap {
    line-height: 1;
    display: inline-flex;
    align-items: center;
}

.chat-tick-sent {
    color: rgba(226, 232, 240, 0.62);
}

.chat-tick-read {
    color: #60a5fa;
}

.chat-action {
    font-size: 11px;
    color: rgba(226, 232, 240, 0.72);
}

.chat-reply {
    border-radius: 10px;
    border-left: 3px solid rgba(251, 191, 36, 0.85);
    background: rgba(15, 23, 42, 0.36);
}

.chat-reaction-float {
    position: absolute;
    bottom: -15px;
    min-height: 24px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #111b25;
    padding: 1px 8px 2px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 14px;
    box-shadow: 0 6px 14px rgba(2, 6, 23, 0.45);
    z-index: 2;
}

.chat-reaction-float-me {
    left: 12px;
}

.chat-reaction-float-other {
    left: 12px;
}

.chat-reaction-count {
    font-size: 10px;
    color: rgba(226, 232, 240, 0.8);
    font-weight: 700;
}

@media (max-width: 480px) {
    .chat-body {
        font-size: 14px;
    }
    .chat-bubble {
        max-width: 90%;
    }
}

.chat-input {
    width: 100%;
    min-height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: #0f172a;
    color: #e5e7eb;
    padding: 0 12px;
    font-size: 14px;
}

.chat-input::placeholder {
    color: #8fa0b8;
}

.chat-input:focus {
    outline: none;
    border-color: rgba(245, 166, 35, 0.55);
    box-shadow: 0 0 0 2px rgba(245, 166, 35, 0.18);
}

.chat-send-btn {
    height: 42px;
    width: 42px;
    border-radius: 999px;
    border: 1px solid rgba(245, 166, 35, 0.45);
    background: linear-gradient(180deg, rgba(245, 166, 35, 0.95), rgba(224, 142, 25, 0.95));
    color: #10131a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    transition: transform .12s ease, opacity .12s ease;
}

.chat-send-btn:active {
    transform: scale(0.96);
}

.chat-send-btn:disabled {
    opacity: 0.45;
}

.chat-reply-composer {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    background: rgba(15, 23, 42, 0.78);
    padding: 8px 10px 7px;
}

.chat-reply-composer-name {
    font-size: 12px;
    font-weight: 800;
    color: #c4b5fd;
    margin-bottom: 2px;
}

.chat-reply-composer-body {
    font-size: 12px;
    color: rgba(226, 232, 240, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 380px;
}

.chat-reply-composer-close {
    color: rgba(226, 232, 240, 0.72);
    flex: 0 0 auto;
}
</style>

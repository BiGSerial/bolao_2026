<template>
    <div
        class="pwa-page"
        :data-shell-swipe-lock="activeTab === 'chat' ? '1' : null"
        :data-pwa-chat-active="activeTab === 'chat' ? '1' : null"
    >

        <!-- Pool header skeleton -->
        <div v-if="loadingPool" class="pwa-section space-y-2 pb-4">
            <SkeletonBlock height="18px" width="60%" />
            <SkeletonBlock height="12px" width="40%" />
        </div>

        <!-- Pool header -->
        <div v-else-if="pool" class="pwa-section pb-3">
            <!-- Competition badge -->
            <p class="text-[10px] font-bold uppercase tracking-widest text-bolao-muted2 mb-1">
                {{ pool.competition?.name ?? '' }}
            </p>
            <h1 class="text-lg font-bold text-white leading-tight">{{ pool.name }}</h1>
            <div v-if="pool.membership" class="mt-2 flex items-center gap-2 flex-wrap">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-bolao-accent/15 text-bolao-accent uppercase tracking-wide">
                    {{ roleLabel(pool.membership.role) }}
                </span>
                <span v-if="pool.membership.sector" class="text-[10px] text-bolao-muted">{{ pool.membership.sector }}</span>
                <!-- Pending predictions badge -->
                <span v-if="openCount > 0" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-bolao-red/15 text-bolao-red flex items-center gap-1">
                    <i class="ti ti-edit text-[10px]"></i>
                    {{ openCount }} palpite{{ openCount !== 1 ? 's' : '' }} pendente{{ openCount !== 1 ? 's' : '' }}
                </span>
            </div>
        </div>

        <!-- Sticky tabs -->
        <div class="pwa-tabs-bar">
            <button
                v-for="tab in mainTabs"
                :key="tab.id"
                class="pwa-tab-btn"
                :class="activeTab === tab.id ? 'active' : ''"
                @click="activeTab = tab.id"
            >
                <i class="ti text-sm mr-1" :class="tab.icon"></i>
                {{ tab.label }}
            </button>
        </div>

        <!-- ─── INFO tab ─── -->
        <div v-show="activeTab === 'info'">
            <div v-if="pool" class="pwa-section pt-4 pb-6 space-y-3">

                <!-- Descrição -->
                <div v-if="pool.description" class="info-card">
                    <p class="info-card-label"><i class="ti ti-align-left"></i> Descrição</p>
                    <p class="info-card-body">{{ pool.description }}</p>
                </div>

                <!-- Regulamento -->
                <div v-if="pool.instructions" class="info-card">
                    <p class="info-card-label"><i class="ti ti-clipboard-text"></i> Regulamento</p>
                    <p class="info-card-body whitespace-pre-wrap">{{ pool.instructions }}</p>
                </div>

                <!-- Pontuação -->
                <div class="info-card">
                    <p class="info-card-label"><i class="ti ti-star"></i> Pontuação por palpite</p>
                    <div class="info-score-grid">
                        <div class="info-score-item">
                            <span class="info-score-val">{{ pool.scoring?.points_exact_score ?? 0 }}</span>
                            <span class="info-score-lbl">Placar exato</span>
                        </div>
                        <div class="info-score-item">
                            <span class="info-score-val">{{ pool.scoring?.points_correct_result ?? 0 }}</span>
                            <span class="info-score-lbl">Resultado certo</span>
                        </div>
                        <div class="info-score-item">
                            <span class="info-score-val">{{ pool.scoring?.points_correct_goals ?? 0 }}</span>
                            <span class="info-score-lbl">Gols de um time</span>
                        </div>
                    </div>
                </div>

                <!-- Regras de palpite -->
                <div class="info-card">
                    <p class="info-card-label"><i class="ti ti-lock"></i> Regras de palpite</p>
                    <div class="space-y-2 mt-1">
                        <div class="info-rule-row" :class="pool.closed_predictions ? 'rule-on' : 'rule-off'">
                            <i class="ti shrink-0" :class="pool.closed_predictions ? 'ti-lock text-rose-400' : 'ti-lock-open text-emerald-400'"></i>
                            <div>
                                <p class="font-semibold text-[13px]">Palpite único</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    {{ pool.closed_predictions
                                        ? 'Ativo — após o início do 1º jogo, nenhum palpite pode ser alterado.'
                                        : 'Desativado — você pode editar palpites conforme as regras abaixo.' }}
                                </p>
                            </div>
                        </div>
                        <div v-if="!pool.closed_predictions" class="info-rule-row" :class="pool.allow_prediction_changes ? 'rule-on' : 'rule-off'">
                            <i class="ti shrink-0" :class="pool.allow_prediction_changes ? 'ti-pencil text-sky-400' : 'ti-pencil-off text-slate-500'"></i>
                            <div>
                                <p class="font-semibold text-[13px]">Edição de palpite</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    {{ pool.allow_prediction_changes
                                        ? `Permitida até ${pool.prediction_lock_minutes ?? 10} minuto(s) antes de cada partida.`
                                        : 'Desativada — palpite enviado não pode ser alterado.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critérios de desempate -->
                <div v-if="pool.tie_breakers?.length" class="info-card">
                    <p class="info-card-label"><i class="ti ti-sort-descending"></i> Desempate (em ordem)</p>
                    <ol class="mt-2 space-y-1">
                        <li v-for="(tb, i) in pool.tie_breakers" :key="tb" class="flex items-center gap-2 text-[13px] text-slate-200">
                            <span class="w-5 h-5 rounded-full bg-white/[0.06] flex items-center justify-center text-[10px] font-bold text-bolao-muted shrink-0">{{ i + 1 }}</span>
                            {{ tieBreakerLabel(tb) }}
                        </li>
                    </ol>
                </div>

                <!-- Setores -->
                <div v-if="pool.sectors?.length" class="info-card">
                    <p class="info-card-label"><i class="ti ti-building"></i> Setores</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span v-for="s in pool.sectors" :key="s" class="text-[12px] px-3 py-1 rounded-full border border-white/10 bg-white/[0.04] text-slate-300">{{ s }}</span>
                    </div>
                </div>

                <!-- Código de convite (visível para todos os membros ativos) -->
                <div v-if="pool.invite_code && pool.membership?.status === 'active'" class="info-card">
                    <p class="info-card-label"><i class="ti ti-share"></i> Código de convite</p>
                    <button class="invite-code-block" @click="copyInviteCode">
                        <span class="invite-code-text">{{ pool.invite_code }}</span>
                        <span class="invite-code-copy" :class="inviteCodeCopied ? 'text-emerald-400' : ''">
                            <i class="ti" :class="inviteCodeCopied ? 'ti-check' : 'ti-copy'"></i>
                            {{ inviteCodeCopied ? 'Copiado!' : 'Copiar' }}
                        </span>
                    </button>
                </div>

                <div v-if="!pool.description && !pool.instructions" class="text-center py-6 text-sm text-bolao-muted">
                    <i class="ti ti-info-circle text-2xl block mb-2 text-bolao-muted2"></i>
                    Este bolão ainda não tem descrição ou regulamento cadastrado.
                </div>
            </div>
        </div>

        <!-- ─── PALPITES tab ─── -->
        <div v-show="activeTab === 'predictions'">
            <div v-if="pool?.membership?.status === 'active'" class="pwa-section pt-3 pb-1">
                <button
                    type="button"
                    class="w-full rounded-xl border border-bolao-accent/30 bg-bolao-accent/10 px-4 py-3 text-left text-bolao-accent flex items-center gap-3 disabled:opacity-50"
                    :disabled="downloadingPdf"
                    @click="downloadPredictionsPdf"
                >
                    <span class="h-9 w-9 shrink-0 rounded-lg bg-bolao-accent/15 flex items-center justify-center">
                        <i class="ti text-xl" :class="downloadingPdf ? 'ti-loader-2 animate-spin' : 'ti-file-type-pdf'"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold">
                            {{ downloadingPdf ? 'Gerando documento...' : 'Baixar tabela de palpites' }}
                        </span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">
                            PDF com todos os jogos, seus palpites, resultados e pontos
                        </span>
                    </span>
                    <i class="ti ti-download text-lg"></i>
                </button>
            </div>

            <!-- Loading inicial -->
            <div v-if="loadingPredictions" class="pwa-section space-y-3 pt-2">
                <SkeletonCard v-for="i in 4" :key="i" />
            </div>

            <!-- Lista infinita -->
            <div v-else class="space-y-5">
                <div v-if="openPredictions.length || finishedPredictions.length" class="pwa-section space-y-3 pt-2">
                    <div class="prediction-filter-row" role="tablist" aria-label="Filtrar palpites">
                        <button
                            v-for="option in predictionFilterOptions"
                            :key="option.value"
                            type="button"
                            class="prediction-filter-chip"
                            :class="{ active: predictionFilter === option.value }"
                            :disabled="option.count === 0"
                            @click="setPredictionFilter(option.value)"
                        >
                            <i class="ti" :class="option.icon"></i>
                            <span>{{ option.label }}</span>
                            <strong>{{ option.count }}</strong>
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2 justify-end">
                        <button class="bulk-action-btn" :disabled="!todayPredictions.length" @click="setPredictionFilter('today')">
                            <i class="ti ti-calendar-event text-[13px]"></i>
                            {{ todayPredictions.length ? 'Filtrar hoje' : 'Sem jogos hoje' }}
                        </button>
                        <button class="bulk-action-btn" :disabled="savingBulk" @click="openBulkModal">
                            <i class="ti ti-layout-grid-add text-[13px]"></i>
                            {{ savingBulk ? 'Aplicando...' : 'Palpite em massa' }}
                        </button>
                        <button class="bulk-action-btn" :disabled="savingApplyAll" @click="openApplyAllModal">
                            <i class="ti ti-copy text-[13px]"></i>
                            {{ savingApplyAll ? 'Replicando...' : 'Replicar' }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredOpenPredictions.length" class="pwa-section space-y-3">

                <div v-if="showBatchPredictionSubmit" class="rounded-lg border border-amber-400/30 bg-amber-400/10 p-3 text-[11px] text-amber-200">
                    <i class="ti ti-lock text-[11px] mr-1"></i>
                    Palpite único ativo — preencha todos os jogos e toque em "Salvar palpites". Após envio, não é possível alterar.
                </div>
                <div v-if="showBatchPredictionSubmit" class="flex justify-end">
                    <button class="pwa-btn-primary" :disabled="savingBatchPredictions" @click="saveBatchPredictions">
                        {{ savingBatchPredictions ? 'Salvando...' : `Salvar palpites (${Object.keys(predictionDrafts).length})` }}
                    </button>
                </div>

                <div
                    v-for="item in visibleOpenPredictions"
                    :key="item.match.id"
                    :id="matchCardId(item.match.id)"
                >
                    <PredictionCard
                        :item="item"
                        :pool-id="poolId"
                        :editing-blocked-reason="predictionEditingBlockedReason"
                        :batch-mode="!!pool?.closed_predictions"
                        :batch-home-score="predictionDrafts[item.match.id]?.home_score"
                        :batch-away-score="predictionDrafts[item.match.id]?.away_score"
                        :batch-saving="savingBatchPredictions"
                        @changed="onPredictionDraftChanged"
                        @saved="onPredictionSaved"
                    />
                </div>

                <!-- Sentinel de scroll infinito -->
                <div ref="sentinelEl" class="h-4"></div>

                <!-- Spinner de carregamento de mais itens -->
                <div v-if="hasMoreToShow" class="flex justify-center py-3">
                    <i class="ti ti-loader-2 animate-spin text-bolao-muted2 text-xl"></i>
                </div>

                <!-- Fim da lista -->
                <div v-else class="text-center py-3 text-[11px] text-bolao-muted2">
                    {{ filteredOpenPredictions.length }} jogo(s) nesta lista
                </div>
                </div>

                <div v-if="filteredFinishedPredictions.length" class="pwa-section space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-bc text-lg font-bold uppercase text-white">Seus palpites encerrados</p>
                            <p class="text-[11px] text-bolao-muted2">Histórico somente para consulta</p>
                        </div>
                        <span class="rounded-full bg-white/[0.06] px-2 py-1 text-[10px] font-bold text-bolao-muted">
                            {{ filteredFinishedPredictions.length }}
                        </span>
                    </div>

                    <div
                        v-for="item in filteredFinishedPredictions"
                        :key="`finished-${item.match.id}`"
                        :id="matchCardId(item.match.id)"
                    >
                        <PredictionCard
                            :item="item"
                            :pool-id="poolId"
                        />
                    </div>
                </div>

                <!-- Sem jogos ou palpites encerrados -->
                <div v-if="!filteredOpenPredictions.length && !filteredFinishedPredictions.length" class="pwa-section text-center py-14">
                    <i class="ti ti-calendar-check text-4xl text-bolao-muted2 mb-3 block"></i>
                    <p class="text-sm text-bolao-muted">
                        Nenhum jogo encontrado neste filtro.
                    </p>
                </div>
            </div>
        </div>

        <!-- ─── RANKING tab ─── -->
        <div v-show="activeTab === 'ranking'">
            <div v-if="loadingRanking" class="pwa-section space-y-2 pt-4">
                <SkeletonCard v-for="i in 5" :key="i" />
            </div>

            <div v-else-if="ranking.length" class="pwa-section pt-3 space-y-1.5">
                <!-- Header -->
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[10px] text-bolao-muted2">
                        Ranking ao vivo · atualizado {{ rankingCalcAt }}
                    </p>
                    <button class="text-[10px] text-bolao-accent font-bold flex items-center gap-1" @click="loadRanking">
                        <i class="ti ti-refresh text-xs"></i> Atualizar
                    </button>
                </div>

                <!-- My position highlight (if not in top view) -->
                <div v-if="myRow && myRow.position > 10"
                     class="flex items-center gap-2 p-3 rounded-xl bg-bolao-accent/[0.07] border border-bolao-accent/20 mb-3">
                    <span class="text-xs font-bold text-bolao-accent">Sua posição: {{ myRow.position }}º</span>
                    <span class="text-xs text-bolao-muted">— {{ myRow.points_total ?? 0 }} pts</span>
                </div>

                <RankingRow
                    v-for="row in rankingWindow"
                    :key="row.user?.id"
                    :row="row"
                    clickable
                    @select="openUserPredictions"
                />
            </div>

            <div v-else class="pwa-section text-center py-14">
                <i class="ti ti-trophy-off text-4xl text-bolao-muted2 mb-3 block"></i>
                <p class="text-sm text-bolao-muted">Ranking ainda não disponível.</p>
            </div>
        </div>

        <!-- ─── CHAT tab ─── -->
        <div v-show="activeTab === 'chat'">
            <!-- Regras do bolão -->
            <div v-if="pool" class="pwa-section pt-2 pb-0">
                <div class="chat-rules-banner">
                    <i class="ti ti-info-circle text-bolao-accent shrink-0 mt-0.5"></i>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-bolao-accent mb-0.5">Regra: palpite único</p>
                        <p class="text-[11px] text-slate-400 leading-snug">
                            O palpite não pode ser alterado após o início do primeiro jogo da competição.
                            Apenas membros <strong>ativos</strong> participam do chat.
                        </p>
                        <p v-if="pool.instructions" class="text-[11px] text-slate-400 mt-1 leading-snug">
                            {{ pool.instructions }}
                        </p>
                    </div>
                </div>
            </div>
            <PoolChatPanel
                v-if="pool"
                :pool-id="poolId"
                :user-id="Number(auth.user?.id || 0)"
            />
        </div>

        <!-- ─── GESTÃO tab ─── -->
        <div v-show="activeTab === 'manage'">
            <div v-if="!canManage" class="pwa-section text-center py-14">
                <i class="ti ti-lock text-4xl text-bolao-muted2 mb-3 block"></i>
                <p class="text-sm text-bolao-muted">Você não tem permissão de gestão neste bolão.</p>
            </div>

            <div v-else class="pwa-section pt-3 space-y-3">
                <div class="card-box">
                    <p class="text-xs text-bolao-muted2 uppercase tracking-wider mb-2">Convidar usuário</p>
                    <div class="space-y-2">
                        <input v-model="inviteEmail" type="email" class="field" placeholder="email@exemplo.com">
                        <input v-model="inviteSector" type="text" class="field" placeholder="Setor (opcional)">
                        <button class="pwa-btn-primary w-full" :disabled="inviteLoading" @click="sendInvite">
                            {{ inviteLoading ? 'Enviando...' : 'Enviar convite' }}
                        </button>
                    </div>
                </div>

                <div class="card-box">
                    <p class="text-xs text-bolao-muted2 uppercase tracking-wider mb-2">Pendentes</p>
                    <div v-if="loadingMembers" class="text-xs text-bolao-muted">Carregando membros...</div>
                    <div v-else-if="pendingMembers.length" class="space-y-2">
                        <div v-for="m in pendingMembers" :key="m.id" class="member-row">
                            <div class="min-w-0">
                                <p class="text-sm text-white truncate">{{ m.user?.name }}</p>
                                <p class="text-[11px] text-bolao-muted truncate">{{ m.user?.email }}</p>
                            </div>
                            <button class="mini-action" :disabled="manageBusyMemberId===m.id" @click="approveMember(m)">Aprovar</button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-bolao-muted">Sem pendências.</p>
                </div>

                <div class="card-box">
                    <p class="text-xs text-bolao-muted2 uppercase tracking-wider mb-2">Ativos</p>
                    <div v-if="activeMembers.length" class="space-y-2">
                        <div v-for="m in activeMembers" :key="m.id" class="member-row">
                            <div class="min-w-0">
                                <p class="text-sm text-white truncate">{{ m.user?.name }}</p>
                                <p class="text-[11px] text-bolao-muted truncate">{{ roleLabel(m.role) }}<span v-if="m.sector"> · {{ m.sector }}</span></p>
                            </div>
                            <div class="flex gap-1">
                                <button v-if="m.role !== 'owner'" class="mini-action" :disabled="manageBusyMemberId===m.id" @click="promoteManager(m)">
                                    {{ m.role === 'manager' ? 'Membro' : 'Gestor' }}
                                </button>
                                <button v-if="m.role !== 'owner'" class="mini-action danger" :disabled="manageBusyMemberId===m.id" @click="removeMember(m)">
                                    Remover
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-bolao-muted">Sem membros ativos.</p>
                </div>

                <div v-if="isOwner" class="card-box border border-red-500/30 bg-red-500/[0.05]">
                    <p class="text-xs text-red-300 uppercase tracking-wider mb-2">Excluir bolão</p>
                    <p class="text-[11px] text-red-200/90 mb-2">
                        Ação irreversível. Será solicitada confirmação antes de excluir.
                    </p>
                    <button
                        class="mini-action danger w-full justify-center"
                        :disabled="deletingPool"
                        @click="destroyPool"
                    >
                        {{ deletingPool ? 'Excluindo...' : 'Excluir bolão' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── CONFIGURAÇÕES tab ─── -->
        <div v-show="activeTab === 'settings'">
            <div v-if="!canManage" class="pwa-section text-center py-14">
                <i class="ti ti-lock text-4xl text-bolao-muted2 mb-3 block"></i>
                <p class="text-sm text-bolao-muted">Você não tem permissão para configurar este bolão.</p>
            </div>

            <div v-else class="pwa-section pt-3 space-y-3">
                <div class="card-box">
                    <p class="text-xs text-bolao-muted2 uppercase tracking-wider mb-2">Configurações do bolão</p>
                    <div class="space-y-2">
                        <p class="text-[11px] text-bolao-muted">Defina as regras abaixo para calcular os pontos de cada palpite.</p>
                        <input v-model="configForm.name" type="text" class="field" placeholder="Nome do bolão">
                        <textarea v-model="configForm.description" class="field min-h-[64px]" placeholder="Descrição"></textarea>
                        <textarea v-model="configForm.instructions" class="field min-h-[80px]" placeholder="Regulamento"></textarea>
                        <div class="grid grid-cols-2 gap-2">
                            <select v-model="configForm.visibility" class="field">
                                <option value="private">Privado</option>
                                <option value="invite_only">Somente convite</option>
                                <option value="public">Público</option>
                            </select>
                            <select v-model="configForm.status" class="field">
                                <option value="active">Ativo</option>
                                <option value="blocked">Bloqueado</option>
                                <option value="archived">Arquivado</option>
                            </select>
                        </div>

                        <label class="flex items-start gap-3 rounded-xl border border-white/15 bg-[#161c28] px-3 py-3 cursor-pointer">
                            <input v-model="configForm.allow_prediction_changes" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-white/30 bg-[#0f131b] accent-[#f5a623]">
                            <span class="min-w-0">
                                <span class="block text-[16px] leading-tight font-semibold text-slate-100">Permitir edição de palpite</span>
                                <span class="mt-1 block text-[14px] leading-snug text-slate-400">Participante pode alterar o palpite até o bloqueio.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-white/15 bg-[#161c28] px-3 py-3 cursor-pointer">
                            <input v-model="configForm.closed_predictions" type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-white/30 bg-[#0f131b] accent-[#f5a623]">
                            <span class="min-w-0">
                                <span class="block text-[16px] leading-tight font-semibold text-slate-100">Palpite fechado</span>
                                <span class="mt-1 block text-[14px] leading-snug text-slate-400">Após o início da 1ª partida do bolão, bloqueia novos envios e alterações.</span>
                            </span>
                        </label>

                        <div v-if="configForm.allow_prediction_changes" class="space-y-1">
                            <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Minutos antes da partida para bloquear edição</label>
                            <input v-model.number="configForm.prediction_lock_minutes" type="number" min="10" class="field" />
                        </div>
                        <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                            <p class="text-[11px] font-semibold text-slate-200">Setores / Departamentos</p>
                            <p class="text-[11px] text-bolao-muted">Use setores para organizar participantes no bolão.</p>

                            <div class="flex gap-2">
                                <input
                                    v-model="newSector"
                                    type="text"
                                    class="field"
                                    maxlength="80"
                                    placeholder="Nome do setor..."
                                    @keydown.enter.prevent="addSector"
                                >
                                <button type="button" class="mini-action" @click="addSector">Adicionar</button>
                            </div>

                            <div v-if="!configForm.sectors.length" class="text-[11px] text-bolao-muted border border-white/10 rounded-lg p-2">
                                Nenhum setor configurado.
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <div
                                    v-for="(sector, index) in configForm.sectors"
                                    :key="`${sector}-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1"
                                >
                                    <span class="text-[12px] text-slate-100">{{ sector }}</span>
                                    <button type="button" class="text-bolao-red text-[12px] leading-none" @click="removeSector(index)">✕</button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                            <p class="text-[11px] font-semibold text-slate-200">Pontuação por acerto</p>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-1">
                                    <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Placar exato</label>
                                    <input v-model.number="configForm.points_exact_score" type="number" min="0" max="20" class="field" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Resultado</label>
                                    <input v-model.number="configForm.points_correct_result" type="number" min="0" max="20" class="field" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] text-bolao-muted2 uppercase tracking-wide">Gols time</label>
                                    <input v-model.number="configForm.points_correct_goals" type="number" min="0" max="20" class="field" />
                                </div>
                            </div>
                            <p class="text-[11px] text-bolao-muted">
                                Exemplo atual: acerto exato vale <b>{{ configForm.points_exact_score }}</b>,
                                acerto de vencedor/empate vale <b>{{ configForm.points_correct_result }}</b>,
                                e cada gol de time correto vale <b>{{ configForm.points_correct_goals }}</b>.
                            </p>
                        </div>

                        <div class="rounded-lg border border-white/10 bg-white/[0.03] p-2 space-y-2">
                            <p class="text-[11px] font-semibold text-slate-200">Critérios de desempate</p>
                            <p class="text-[11px] text-bolao-muted">Só aparecem critérios com pontuação maior que zero.</p>

                            <div class="flex gap-2">
                                <select v-model="newTieBreaker" class="field">
                                    <option value="">Selecionar critério...</option>
                                    <option v-for="opt in availableTieBreakers" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <button type="button" class="mini-action" @click="addTieBreaker">Adicionar</button>
                            </div>

                            <div v-if="!configForm.tie_breakers.length" class="text-[11px] text-bolao-muted border border-white/10 rounded-lg p-2">
                                Sem desempate configurado.
                            </div>
                            <div v-else class="space-y-2">
                                <p class="text-[11px] text-bolao-muted">No celular, use ↑ ↓ para ordenar. No desktop, também pode arrastar.</p>
                                <div
                                    v-for="(criterion, index) in configForm.tie_breakers"
                                    :key="criterion"
                                    class="rank-row"
                                    :class="{
                                        'drag-active': draggingTieBreakerIndex === index,
                                        'drop-target': touchTieBreakerIndex === index || dragOverTieBreakerIndex === index,
                                    }"
                                    :data-tie-index="index"
                                    draggable="true"
                                    @dragstart="onTieBreakerDragStart(index)"
                                    @dragover.prevent="onTieBreakerDragOver(index)"
                                    @drop="onTieBreakerDrop(index)"
                                    @dragend="onTieBreakerDragEnd"
                                    @touchstart="onTieBreakerTouchStart($event, index)"
                                    @touchmove.prevent="onTieBreakerTouchMove"
                                    @touchend="onTieBreakerTouchEnd"
                                    @touchcancel="onTieBreakerTouchEnd"
                                >
                                    <div class="min-w-0 flex items-center gap-2">
                                        <span class="drag-handle" aria-hidden="true">⋮⋮</span>
                                        <span class="prio-badge">Prioridade {{ index + 1 }}</span>
                                        <span class="text-[12px] text-slate-100 truncate">{{ tieBreakerLabels[criterion] ?? criterion }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="mini-action" :disabled="index===0" @click="moveTieBreaker(index, 'up')">↑</button>
                                        <button type="button" class="mini-action" :disabled="index===configForm.tie_breakers.length-1" @click="moveTieBreaker(index, 'down')">↓</button>
                                        <button type="button" class="mini-action danger" @click="removeTieBreakerAt(index)">Remover</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="pwa-btn-primary w-full" :disabled="savingConfig" @click="savePoolSettings">
                            {{ savingConfig ? 'Salvando...' : 'Salvar configurações' }}
                        </button>
                    </div>
                </div>

                <div v-if="isOwner" class="card-box border border-red-500/30 bg-red-500/[0.05]">
                    <p class="text-xs text-red-300 uppercase tracking-wider mb-2">Excluir bolão</p>
                    <p class="text-[11px] text-red-200/90 mb-2">
                        Ação irreversível. Será solicitada confirmação antes de excluir.
                    </p>
                    <button
                        class="mini-action danger w-full justify-center"
                        :disabled="deletingPool"
                        @click="destroyPool"
                    >
                        {{ deletingPool ? 'Excluindo...' : 'Excluir bolão' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="selectedRankingUser" class="fixed inset-0 z-50 flex items-end bg-black/70" @click.self="closeUserPredictions">
            <div class="user-predictions-sheet">
                <div class="flex items-start justify-between gap-3 border-b border-white/10 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-bolao-muted2">Palpites de</p>
                        <h2 class="truncate text-base font-bold text-white">{{ selectedRankingUser.user?.name ?? selectedUserPredictions?.user?.name ?? 'Participante' }}</h2>
                        <p class="mt-1 text-[11px] text-bolao-muted">
                            {{ selectedUserPredictions?.visibility?.all_matches
                                ? 'Palpite fechado: futuros liberados'
                                : 'Apenas jogos fechados ou passados' }}
                        </p>
                    </div>
                    <button class="user-predictions-close" @click="closeUserPredictions">
                        <i class="ti ti-x"></i>
                    </button>
                </div>

                <div class="max-h-[70dvh] overflow-y-auto px-4 py-3">
                    <div v-if="loadingUserPredictions" class="space-y-2">
                        <SkeletonCard v-for="i in 4" :key="i" />
                    </div>

                    <div v-else-if="selectedUserPredictionItems.length" class="space-y-2">
                        <div class="prediction-filter-row pb-1" role="tablist" aria-label="Filtrar palpites do participante">
                            <button
                                v-for="option in selectedUserPredictionFilterOptions"
                                :key="option.value"
                                type="button"
                                class="prediction-filter-chip compact"
                                :class="{ active: selectedUserPredictionFilter === option.value }"
                                :disabled="option.count === 0"
                                @click="setSelectedUserPredictionFilter(option.value)"
                            >
                                <i class="ti" :class="option.icon"></i>
                                <span>{{ option.label }}</span>
                                <strong>{{ option.count }}</strong>
                            </button>
                        </div>

                        <router-link
                            v-for="item in filteredSelectedUserPredictionItems"
                            :key="item.match.id"
                            :to="{ name: 'pool-match-detail', params: { poolId: poolId, matchId: item.match.id } }"
                            :id="userPredictionCardId(item.match.id)"
                            class="user-prediction-row"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-bolao-muted2">
                                    <span>{{ matchStatusLabel(item.match.status) }}</span>
                                    <span v-if="item.match.matchday">Rodada {{ item.match.matchday }}</span>
                                    <span>{{ formatMatchDateTime(item.match.local_date) }}</span>
                                </div>
                                <div class="mt-1 flex min-w-0 items-center gap-1.5 text-sm font-bold text-white">
                                    <img v-if="item.match.home_team?.crest" :src="item.match.home_team.crest" class="user-prediction-flag" alt="">
                                    <span class="truncate">{{ displayTeamName(item.match.home_team, 'Casa') }}</span>
                                    <span class="shrink-0 text-bolao-muted2">x</span>
                                    <img v-if="item.match.away_team?.crest" :src="item.match.away_team.crest" class="user-prediction-flag" alt="">
                                    <span class="truncate">{{ displayTeamName(item.match.away_team, 'Fora') }}</span>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <div v-if="item.prediction" class="user-prediction-score">
                                    {{ item.prediction.home_score }}×{{ item.prediction.away_score }}
                                </div>
                                <div v-else class="text-[10px] font-bold uppercase text-bolao-muted2">Sem palpite</div>
                                <div class="mt-1 flex items-center justify-end gap-1.5 text-[10px] font-bold">
                                    <span v-if="hasRealScore(item)" class="text-slate-400">
                                        Real {{ item.match.score.home }}×{{ item.match.score.away }}
                                    </span>
                                    <span v-if="item.prediction?.points !== null && item.prediction?.points !== undefined" class="user-prediction-points">
                                        {{ item.prediction.points }} pts
                                    </span>
                                </div>
                            </div>
                        </router-link>

                        <div v-if="!filteredSelectedUserPredictionItems.length" class="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-8 text-center text-sm text-bolao-muted">
                            Nenhum palpite encontrado neste filtro.
                        </div>
                    </div>

                    <div v-else class="py-10 text-center">
                        <i class="ti ti-lock text-3xl text-bolao-muted2"></i>
                        <p class="mt-2 text-sm text-bolao-muted">Nenhum palpite visível ainda.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useAuthStore } from '../store/auth';
import { getPools, getPool, updatePool, deletePool } from '../api/pools';
import { downloadPoolPredictionsPdf, getPoolPredictions, getPoolUserPredictions, savePrediction } from '../api/predictions';
import { getLiveRanking } from '../api/rankings';
import { getPoolMembers, updatePoolMember, removePoolMember, inviteToPool } from '../api/pool-members';
import SkeletonBlock from '../components/ui/SkeletonBlock.vue';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import PredictionCard from '../components/ui/PredictionCard.vue';
import RankingRow from '../components/ui/RankingRow.vue';
import PoolChatPanel from '../components/ui/PoolChatPanel.vue';

const emit = defineEmits(['set-title']);
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const poolId = computed(() => route.params.id);
const persistedDetailTabKey = computed(() => `pwa_pool_detail_tab_${poolId.value}`);

const BASE_TABS = [
    { id: 'info',        label: 'Info',     icon: 'ti-info-circle' },
    { id: 'predictions', label: 'Palpites', icon: 'ti-pencil' },
    { id: 'ranking',     label: 'Ranking',  icon: 'ti-trophy' },
    { id: 'chat',        label: 'Chat',     icon: 'ti-message-circle' },
];
const MANAGE_TAB = { id: 'manage', label: 'Gestão', icon: 'ti-users' };
const mainTabs = computed(() => canManage.value ? [...BASE_TABS, MANAGE_TAB] : BASE_TABS);
const routeTab = String(route.query.tab || '').toLowerCase();
const storedTab = String(localStorage.getItem(persistedDetailTabKey.value) || '').toLowerCase();
const resolveAllowedTab = (value) => (
    ['info', 'predictions', 'ranking', 'chat', 'manage'].includes(String(value || '').toLowerCase())
        ? String(value).toLowerCase()
        : null
);
const activeTab = ref(resolveAllowedTab(routeTab) ?? resolveAllowedTab(storedTab) ?? 'predictions');

const pool          = ref(null);
const loadingPool   = ref(true);
const predictions        = ref([]);
const loadingPredictions = ref(true);
const visibleCount       = ref(15);
const sentinelEl         = ref(null);
const predictionDrafts   = ref({});
const predictionFilter   = ref('all');
let   infiniteObserver   = null;
const savingBatchPredictions = ref(false);
const savingBulk     = ref(false);
const savingApplyAll = ref(false);
const ranking       = ref([]);
const loadingRanking = ref(true);
const rankingCalcAt = ref('');
const selectedRankingUser = ref(null);
const selectedUserPredictions = ref(null);
const loadingUserPredictions = ref(false);
const selectedUserPredictionFilter = ref('all');
const members = ref([]);
const loadingMembers = ref(false);
const manageBusyMemberId = ref(null);
const inviteEmail = ref('');
const inviteSector = ref('');
const inviteLoading = ref(false);
const inviteCodeCopied = ref(false);
const savingConfig = ref(false);
const deletingPool = ref(false);
const downloadingPdf = ref(false);
const newTieBreaker = ref('');
const newSector = ref('');
const draggedTieBreakerIndex = ref(null);
const touchTieBreakerIndex = ref(null);
const draggingTieBreakerIndex = ref(null);
const dragOverTieBreakerIndex = ref(null);
const configForm = ref({
    name: '',
    description: '',
    instructions: '',
    visibility: 'invite_only',
    status: 'active',
    allow_prediction_changes: true,
    closed_predictions: false,
    allow_pending_member_predictions: true,
    prediction_lock_minutes: 10,
    points_exact_score: 5,
    points_correct_result: 3,
    points_correct_goals: 1,
    correct_goals_mode: 'both_teams',
    sectors: [],
    tie_breakers: [],
});
const tieBreakerOptions = [
    { value: 'exact_scores', label: 'Mais placares exatos' },
    { value: 'correct_results', label: 'Mais resultados corretos' },
    { value: 'correct_home_goals', label: 'Mais gols do mandante corretos' },
    { value: 'correct_away_goals', label: 'Mais gols do visitante corretos' },
    { value: 'predictions_counted', label: 'Mais palpites válidos' },
];
const tieBreakerLabels = Object.fromEntries(tieBreakerOptions.map((o) => [o.value, o.label]));
const enabledTieBreakerOptions = computed(() => {
    const enabled = [];
    if (Number(configForm.value.points_exact_score) > 0) enabled.push('exact_scores');
    if (Number(configForm.value.points_correct_result) > 0) enabled.push('correct_results');
    if (Number(configForm.value.points_correct_goals) > 0) {
        enabled.push('correct_home_goals');
        enabled.push('correct_away_goals');
    }
    enabled.push('predictions_counted');
    return tieBreakerOptions.filter((o) => enabled.includes(o.value));
});
const availableTieBreakers = computed(() =>
    enabledTieBreakerOptions.value.filter((o) => !configForm.value.tie_breakers.includes(o.value)),
);
let wallStartMs = 0;
let monoStartMs = 0;
const clockTampered = ref(false);
let clockGuardTimer = null;

const roleLabel = (r) => ({ owner: 'Dono', manager: 'Gestor', member: 'Membro' }[r] ?? r ?? '');

const TIE_BREAKER_LABELS = {
    exact_scores: 'Mais placares exatos',
    correct_results: 'Mais resultados corretos',
    correct_home_goals: 'Mais gols do mandante corretos',
    correct_away_goals: 'Mais gols do visitante corretos',
    predictions_counted: 'Mais palpites válidos',
};
const tieBreakerLabel = (tb) => TIE_BREAKER_LABELS[tb] ?? tb;

let inviteCodeCopiedTimer = null;
function copyInviteCode() {
    const code = pool.value?.invite_code;
    if (!code) return;
    navigator.clipboard?.writeText(code).catch(() => {});
    inviteCodeCopied.value = true;
    if (inviteCodeCopiedTimer) clearTimeout(inviteCodeCopiedTimer);
    inviteCodeCopiedTimer = setTimeout(() => { inviteCodeCopied.value = false; }, 2000);
}

// Jogos futuros/em andamento visiveis na aba de palpites. Se ja estiverem
// bloqueados, o card fica somente leitura, mas continuam aparecendo em "Todos".
const OPEN_STATUSES = ['TIMED', 'SCHEDULED', 'PRE_MATCH'];
const openPredictions = computed(() =>
    [...predictions.value]
        .filter(i => [...OPEN_STATUSES, 'IN_PLAY', 'PAUSED', 'HALFTIME'].includes(i.match?.status))
        .sort((a, b) => {
            const ta = a.match?.local_date ? new Date(a.match.local_date).getTime() : 0;
            const tb = b.match?.local_date ? new Date(b.match.local_date).getTime() : 0;
            return ta - tb;
        })
);
const finishedPredictions = computed(() =>
    [...predictions.value]
        .filter(i => ['FINISHED', 'AWARDED'].includes(i.match?.status) && i.prediction != null)
        .sort((a, b) => {
            const ta = a.match?.local_date ? new Date(a.match.local_date).getTime() : 0;
            const tb = b.match?.local_date ? new Date(b.match.local_date).getTime() : 0;
            return tb - ta;
        })
);
const todayPredictions = computed(() =>
    [...openPredictions.value, ...finishedPredictions.value].filter((item) => isTodayMatch(item.match)),
);
const filteredOpenPredictions = computed(() => {
    if (predictionFilter.value === 'today') {
        return openPredictions.value.filter((item) => isTodayMatch(item.match));
    }
    if (predictionFilter.value === 'finished') return [];
    return openPredictions.value;
});
const filteredFinishedPredictions = computed(() => {
    if (predictionFilter.value === 'today') {
        return finishedPredictions.value.filter((item) => isTodayMatch(item.match));
    }
    if (predictionFilter.value === 'open') return [];
    return finishedPredictions.value;
});
const visibleOpenPredictions = computed(() => filteredOpenPredictions.value.slice(0, visibleCount.value));
const hasMoreToShow = computed(() => visibleCount.value < filteredOpenPredictions.value.length);
const predictionFilterOptions = computed(() => [
    {
        value: 'all',
        label: 'Todos',
        icon: 'ti-list',
        count: openPredictions.value.length + finishedPredictions.value.length,
    },
    {
        value: 'today',
        label: 'Hoje',
        icon: 'ti-calendar-event',
        count: todayPredictions.value.length,
    },
    {
        value: 'open',
        label: 'Próximos',
        icon: 'ti-pencil',
        count: openPredictions.value.length,
    },
    {
        value: 'finished',
        label: 'Encerrados',
        icon: 'ti-flag-checkered',
        count: finishedPredictions.value.length,
    },
]);

const openCount = computed(() => openPredictions.value.filter((i) => !i.lock?.is_locked && i.prediction == null).length);

const predictionEditingBlockedReason = computed(() => {
    if (!navigator.onLine) return 'Sem conexão. O aplicativo exige validação online de horário para salvar palpite.';
    if (clockTampered.value) return 'Relógio do aparelho inconsistente. Ajuste data/hora automáticas para palpitar.';
    return '';
});
const hasBatchChanges = computed(() => Object.keys(predictionDrafts.value).length > 0);
const showBatchPredictionSubmit = computed(() => !!pool.value?.closed_predictions && hasBatchChanges.value);

async function downloadPredictionsPdf() {
    downloadingPdf.value = true;
    try {
        const response = await downloadPoolPredictionsPdf(poolId.value);
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const disposition = String(response.headers?.['content-disposition'] ?? '');
        const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        link.href = url;
        link.download = encodedName ? decodeURIComponent(encodedName) : `palpites-${pool.value?.slug ?? poolId.value}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch {
        await Swal.fire({
            icon: 'error',
            title: 'Não foi possível gerar o PDF',
            text: 'Tente novamente em alguns instantes.',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#f59e0b',
        });
    } finally {
        downloadingPdf.value = false;
    }
}

const myRow = computed(() => {
    if (!auth.user) return null;
    return ranking.value.find(r => r.user?.id === auth.user.id) ?? null;
});
const rankingWindow = computed(() => centeredUserWindow(ranking.value, auth.user?.id));
const canManage = computed(() => !!pool.value?.permissions?.can_manage);
const isOwner = computed(() => pool.value?.membership?.role === 'owner');
const pendingMembers = computed(() => members.value.filter((m) => m.status === 'pending'));
const activeMembers = computed(() => members.value.filter((m) => m.status === 'active'));
const selectedUserPredictionItems = computed(() => selectedUserPredictions.value?.items ?? []);
const selectedUserTodayPredictionItems = computed(() =>
    selectedUserPredictionItems.value.filter((item) => isTodayMatch(item.match)),
);
const selectedUserFinishedPredictionItems = computed(() =>
    selectedUserPredictionItems.value.filter((item) => ['FINISHED', 'AWARDED'].includes(String(item.match?.status || '').toUpperCase())),
);
const filteredSelectedUserPredictionItems = computed(() => {
    if (selectedUserPredictionFilter.value === 'today') {
        return selectedUserTodayPredictionItems.value;
    }
    if (selectedUserPredictionFilter.value === 'finished') {
        return selectedUserFinishedPredictionItems.value;
    }
    return selectedUserPredictionItems.value;
});
const selectedUserPredictionFilterOptions = computed(() => [
    {
        value: 'all',
        label: 'Todos',
        icon: 'ti-list',
        count: selectedUserPredictionItems.value.length,
    },
    {
        value: 'today',
        label: 'Hoje',
        icon: 'ti-calendar-event',
        count: selectedUserTodayPredictionItems.value.length,
    },
    {
        value: 'finished',
        label: 'Encerrados',
        icon: 'ti-flag-checkered',
        count: selectedUserFinishedPredictionItems.value.length,
    },
]);

function centeredUserWindow(rows, userId, size = 5) {
    const list = Array.isArray(rows) ? rows : [];
    if (list.length <= size) return list;

    const index = list.findIndex((row) => Number(row.user?.id) === Number(userId));
    if (index < 0) return list.slice(0, size);

    const start = Math.max(0, Math.min(index - Math.floor(size / 2), list.length - size));
    return list.slice(start, start + size);
}

function matchStatusLabel(status) {
    return {
        FINISHED: 'Encerrado',
        AWARDED: 'Encerrado',
        IN_PLAY: 'Ao vivo',
        PAUSED: 'Intervalo',
        TIMED: 'Agendado',
        SCHEDULED: 'Agendado',
        PRE_MATCH: 'Em breve',
    }[String(status || '').toUpperCase()] ?? String(status || '');
}

function formatMatchDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function displayTeamName(team, fallback) {
    const raw = String(team?.name ?? team?.tla ?? fallback ?? '').trim();
    return raw || fallback;
}

function matchCardId(matchId) {
    return `pool-match-card-${matchId}`;
}

function userPredictionCardId(matchId) {
    return `user-prediction-card-${matchId}`;
}

function isTodayMatch(match) {
    if (!match?.local_date) return false;
    const matchDate = new Date(match.local_date);
    const today = new Date();

    return matchDate.getFullYear() === today.getFullYear()
        && matchDate.getMonth() === today.getMonth()
        && matchDate.getDate() === today.getDate();
}

function hasRealScore(item) {
    return item?.match?.score?.home !== null
        && item?.match?.score?.home !== undefined
        && item?.match?.score?.away !== null
        && item?.match?.score?.away !== undefined;
}

function setPredictionFilter(value) {
    predictionFilter.value = ['all', 'today', 'open', 'finished'].includes(value) ? value : 'all';
    visibleCount.value = 15;
    nextTick(() => setupInfiniteScroll());
}

function setSelectedUserPredictionFilter(value) {
    selectedUserPredictionFilter.value = ['all', 'today', 'finished'].includes(value) ? value : 'all';
}

async function scrollToTodayGames() {
    const todayItem = todayPredictions.value[0];
    if (!todayItem?.match?.id) return;

    activeTab.value = 'predictions';

    const openIndex = openPredictions.value.findIndex((item) => Number(item.match?.id) === Number(todayItem.match.id));
    if (openIndex >= 0) {
        visibleCount.value = Math.max(visibleCount.value, openIndex + 1);
    }

    await nextTick();

    document.getElementById(matchCardId(todayItem.match.id))?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}

async function scrollUserModalToTodayGames() {
    const todayItem = selectedUserTodayPredictionItems.value[0];
    if (!todayItem?.match?.id) return;

    await nextTick();

    document.getElementById(userPredictionCardId(todayItem.match.id))?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}

async function openUserPredictions(row) {
    const userId = Number(row?.user?.id || 0);
    if (!userId) return;

    selectedRankingUser.value = row;
    selectedUserPredictions.value = null;
    selectedUserPredictionFilter.value = 'all';
    loadingUserPredictions.value = true;

    try {
        const res = await getPoolUserPredictions(poolId.value, userId, { per_page: 200 });
        selectedUserPredictions.value = res.data.data;
    } catch {
        selectedUserPredictions.value = {
            user: row.user,
            items: [],
            visibility: { all_matches: false },
        };
    } finally {
        loadingUserPredictions.value = false;
    }
}

function closeUserPredictions() {
    selectedRankingUser.value = null;
    selectedUserPredictions.value = null;
    loadingUserPredictions.value = false;
}

async function loadPool() {
    loadingPool.value = true;
    try {
        const res = await getPool(poolId.value);
        applyPoolData(res.data.data);
        emit('set-title', pool.value.name);
    } finally {
        loadingPool.value = false;
    }
}

async function savePoolSettings() {
    if (!canManage.value || !pool.value) return;
    savingConfig.value = true;
    try {
        const res = await updatePool(poolId.value, configForm.value);
        const updatedPool = res?.data?.data?.pool;
        if (updatedPool) applyPoolData(updatedPool);
        await Swal.fire({
            icon: 'success',
            title: 'Configurações salvas',
            timer: 1400,
            showConfirmButton: false,
            background: '#0f172a',
            color: '#e2e8f0',
        });
    } catch {
        await Swal.fire({
            icon: 'error',
            title: 'Erro ao salvar',
            text: 'Não foi possível salvar as configurações. Tente novamente.',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        savingConfig.value = false;
    }
}

function applyPoolData(data) {
    pool.value = data;
    configForm.value = {
        name: data?.name ?? '',
        description: data?.description ?? '',
        instructions: data?.instructions ?? '',
        visibility: data?.visibility ?? 'invite_only',
        status: data?.status ?? 'active',
        allow_prediction_changes: !!data?.allow_prediction_changes,
        closed_predictions: !!data?.closed_predictions,
        allow_pending_member_predictions: !!data?.allow_pending_member_predictions,
        prediction_lock_minutes: Number(data?.prediction_lock_minutes ?? 10),
        points_exact_score: Number(data?.scoring?.points_exact_score ?? 5),
        points_correct_result: Number(data?.scoring?.points_correct_result ?? 3),
        points_correct_goals: Number(data?.scoring?.points_correct_goals ?? 1),
        correct_goals_mode: data?.scoring?.correct_goals_mode ?? 'both_teams',
        sectors: Array.isArray(data?.sectors) ? data.sectors : [],
        tie_breakers: Array.isArray(data?.tie_breakers) ? data.tie_breakers : [],
    };
}

function toggleTieBreaker(value) {
    const current = Array.isArray(configForm.value.tie_breakers) ? [...configForm.value.tie_breakers] : [];
    const idx = current.indexOf(value);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else if (current.length < 5) {
        current.push(value);
    }
    configForm.value.tie_breakers = current;
}

function addTieBreaker() {
    const value = String(newTieBreaker.value || '').trim();
    if (!value) return;
    if (!enabledTieBreakerOptions.value.some((o) => o.value === value)) return;
    if (configForm.value.tie_breakers.includes(value)) return;
    if (configForm.value.tie_breakers.length >= 5) return;
    configForm.value.tie_breakers = [...configForm.value.tie_breakers, value];
    newTieBreaker.value = '';
}

function addSector() {
    const sector = String(newSector.value || '').replace(/\s+/g, ' ').trim();
    if (!sector) return;
    const exists = configForm.value.sectors.some((item) => item.toLowerCase() === sector.toLowerCase());
    if (exists) return;
    if (configForm.value.sectors.length >= 30) return;
    configForm.value.sectors = [...configForm.value.sectors, sector];
    newSector.value = '';
}

function removeSector(index) {
    const list = [...configForm.value.sectors];
    if (index < 0 || index >= list.length) return;
    list.splice(index, 1);
    configForm.value.sectors = list;
}

function removeTieBreakerAt(index) {
    const list = [...configForm.value.tie_breakers];
    if (index < 0 || index >= list.length) return;
    list.splice(index, 1);
    configForm.value.tie_breakers = list;
}

function moveTieBreaker(index, direction) {
    const list = [...configForm.value.tie_breakers];
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= list.length) return;
    const item = list[index];
    list.splice(index, 1);
    list.splice(target, 0, item);
    configForm.value.tie_breakers = list;
}

function onTieBreakerDragStart(index) {
    draggedTieBreakerIndex.value = index;
    draggingTieBreakerIndex.value = index;
}

function onTieBreakerDrop(targetIndex) {
    const from = draggedTieBreakerIndex.value;
    draggedTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
    if (from === null || from === targetIndex) return;
    reorderTieBreakers(from, targetIndex);
}

function onTieBreakerDragOver(index) {
    dragOverTieBreakerIndex.value = index;
}

function onTieBreakerDragEnd() {
    draggedTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
}

function onTieBreakerTouchStart(event, index) {
    if (event.target?.closest('.mini-action')) return;
    touchTieBreakerIndex.value = index;
    draggingTieBreakerIndex.value = index;
}

function onTieBreakerTouchMove(event) {
    const from = touchTieBreakerIndex.value;
    const point = event.touches?.[0];
    if (from === null || !point) return;
    const element = document.elementFromPoint(point.clientX, point.clientY);
    const row = element?.closest?.('[data-tie-index]');
    if (!row) return;
    const targetIndex = Number(row.getAttribute('data-tie-index'));
    if (!Number.isInteger(targetIndex) || targetIndex === from) return;
    reorderTieBreakers(from, targetIndex);
    touchTieBreakerIndex.value = targetIndex;
}

function onTieBreakerTouchEnd() {
    touchTieBreakerIndex.value = null;
    draggingTieBreakerIndex.value = null;
    dragOverTieBreakerIndex.value = null;
}

function reorderTieBreakers(from, targetIndex) {
    const list = [...configForm.value.tie_breakers];
    if (from < 0 || from >= list.length || targetIndex < 0 || targetIndex >= list.length) return;
    const [item] = list.splice(from, 1);
    list.splice(targetIndex, 0, item);
    configForm.value.tie_breakers = list;
}

function regenerateDeleteChallenge() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let out = '';
    for (let i = 0; i < 6; i += 1) out += chars[Math.floor(Math.random() * chars.length)];
    return out;
}

async function destroyPool() {
    if (!pool.value || !isOwner.value) return;
    const challenge = regenerateDeleteChallenge();

    const result = await Swal.fire({
        title: 'Excluir bolão',
        html: `
            <div style="text-align:left;font-size:13px;color:#cbd5e1;line-height:1.45">
                <p style="margin:0 0 8px 0">Essa ação é irreversível.</p>
                <p style="margin:0 0 8px 0">Digite a palavra abaixo para confirmar:</p>
                <p style="margin:0 0 8px 0;font-family:monospace;font-size:20px;font-weight:700;color:#fca5a5">${challenge}</p>
            </div>
        `,
        input: 'text',
        inputPlaceholder: 'Digite a palavra de confirmação',
        inputAttributes: { autocapitalize: 'characters' },
        showCancelButton: true,
        confirmButtonText: 'Excluir bolão',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#b91c1c',
        cancelButtonColor: '#334155',
        focusCancel: true,
        preConfirm: (value) => {
            if ((value || '').trim().toUpperCase() !== challenge) {
                Swal.showValidationMessage('Palavra de confirmação inválida.');
                return false;
            }
            return true;
        },
    });

    if (!result.isConfirmed) return;

    deletingPool.value = true;
    try {
        await deletePool(poolId.value);
        router.replace('/pwa/pools');
    } finally {
        deletingPool.value = false;
    }
}

async function loadPredictions() {
    loadingPredictions.value = true;
    visibleCount.value = 15;
    const all = [];
    try {
        // Carrega todo o escopo para manter visíveis os palpites de jogos encerrados.
        let page = 1;
        let totalPages = 1;
        do {
            const res = await getPoolPredictions(poolId.value, { per_page: 200, page });
            const items = res.data.data?.items ?? [];
            all.push(...items);
            totalPages = Number(res.data.meta?.pagination?.total_pages ?? 1) || 1;
            page++;
        } while (page <= totalPages);

        predictions.value = all;
        predictionDrafts.value = {};
    } finally {
        loadingPredictions.value = false;
    }
}

function setupInfiniteScroll() {
    if (infiniteObserver) infiniteObserver.disconnect();
    if (!sentinelEl.value) return;
    infiniteObserver = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting && hasMoreToShow.value) {
            visibleCount.value += 15;
        }
    }, { rootMargin: '200px' });
    infiniteObserver.observe(sentinelEl.value);
}

async function loadRanking() {
    loadingRanking.value = true;
    try {
        const res = await getLiveRanking(poolId.value);
        ranking.value = res.data.data.items ?? [];
        const calcAt = res.data.data.calculated_at;
        rankingCalcAt.value = calcAt
            ? new Date(calcAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : '—';
    } catch {
        ranking.value = [];
        rankingCalcAt.value = '—';
    } finally {
        loadingRanking.value = false;
    }
}

function onPredictionSaved(payload) {
    const matchId = Number(payload?.matchId ?? payload?.match_id ?? 0);
    if (matchId) {
        predictions.value = predictions.value.map((item) => {
            if (Number(item.match?.id) !== matchId) return item;

            return {
                ...item,
                prediction: {
                    ...(item.prediction ?? {}),
                    home_score: Number(payload?.home ?? payload?.home_score ?? 0),
                    away_score: Number(payload?.away ?? payload?.away_score ?? 0),
                },
            };
        });
    }

    // Reload ranking silently on next open
    if (activeTab.value === 'ranking') loadRanking();
}

function onPredictionDraftChanged(payload) {
    const matchId = Number(payload?.match_id ?? 0);
    if (!matchId) return;
    predictionDrafts.value = {
        ...predictionDrafts.value,
        [matchId]: {
            home_score: Number(payload?.home_score ?? 0),
            away_score: Number(payload?.away_score ?? 0),
        },
    };
}

async function saveBatchPredictions() {
    if (!pool.value?.closed_predictions) return;
    const entries = Object.entries(predictionDrafts.value);
    if (!entries.length) return;
    if (predictionEditingBlockedReason.value) {
        await Swal.fire({
            icon: 'warning',
            title: 'Não foi possível salvar',
            text: predictionEditingBlockedReason.value,
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#f59e0b',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'question',
        title: 'Confirmar envio dos palpites?',
        text: `Serão enviados ${entries.length} palpites de uma vez.`,
        showCancelButton: true,
        confirmButtonText: 'Salvar palpites',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
    });
    if (!result.isConfirmed) return;

    savingBatchPredictions.value = true;
    try {
        for (const [matchId, scores] of entries) {
            await savePrediction(poolId.value, matchId, scores.home_score, scores.away_score);
        }
        predictionDrafts.value = {};
        await loadPredictions();
        if (activeTab.value === 'ranking') await loadRanking();
        await Swal.fire({
            icon: 'success',
            title: 'Palpites salvos',
            text: 'Todos os palpites foram enviados com sucesso.',
            timer: 1400,
            showConfirmButton: false,
            background: '#0f172a',
            color: '#e2e8f0',
        });
    } catch (err) {
        await loadPredictions();
        const code = err.response?.data?.error?.code;
        const message = code === 'PREDICTION_RULE_VIOLATION'
            ? (err.response?.data?.error?.message ?? 'Envio bloqueado por regra do bolão.')
            : 'Erro ao salvar palpites em lote. Tente novamente.';
        await Swal.fire({
            icon: 'error',
            title: 'Falha ao salvar',
            text: message,
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        savingBatchPredictions.value = false;
    }
}


function startClockGuard() {
    wallStartMs = Date.now();
    monoStartMs = performance.now();
    clockTampered.value = false;
    if (clockGuardTimer) clearInterval(clockGuardTimer);
    clockGuardTimer = setInterval(() => {
        const wallDelta = Date.now() - wallStartMs;
        const monoDelta = performance.now() - monoStartMs;
        const drift = Math.abs(wallDelta - monoDelta);
        if (drift > 120000) {
            clockTampered.value = true;
        }
    }, 5000);
}

async function loadMembers() {
    if (!canManage.value) return;
    loadingMembers.value = true;
    try {
        const res = await getPoolMembers(poolId.value);
        members.value = res.data.data.items ?? [];
    } finally {
        loadingMembers.value = false;
    }
}

async function approveMember(member) {
    const { isConfirmed } = await Swal.fire({
        title: 'Aprovar membro?',
        html: `Aprovar <strong>${member.user?.name}</strong> no bolão?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Aprovar',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#334155',
    });
    if (!isConfirmed) return;
    manageBusyMemberId.value = member.id;
    try {
        await updatePoolMember(poolId.value, member.id, { status: 'active' });
        await loadMembers();
    } finally {
        manageBusyMemberId.value = null;
    }
}

async function promoteManager(member) {
    const toManager = member.role !== 'manager';
    const { isConfirmed } = await Swal.fire({
        title: toManager ? 'Promover a Gestor?' : 'Rebaixar a Membro?',
        html: `Deseja ${toManager ? 'promover' : 'rebaixar'} <strong>${member.user?.name}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
    });
    if (!isConfirmed) return;
    manageBusyMemberId.value = member.id;
    try {
        await updatePoolMember(poolId.value, member.id, { role: toManager ? 'manager' : 'member' });
        await loadMembers();
    } finally {
        manageBusyMemberId.value = null;
    }
}

async function removeMember(member) {
    const { isConfirmed } = await Swal.fire({
        title: 'Remover membro?',
        html: `Remover <strong>${member.user?.name}</strong> do bolão?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remover',
        cancelButtonText: 'Cancelar',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
    });
    if (!isConfirmed) return;
    manageBusyMemberId.value = member.id;
    try {
        await removePoolMember(poolId.value, member.id);
        await loadMembers();
    } finally {
        manageBusyMemberId.value = null;
    }
}

async function sendInvite() {
    if (!inviteEmail.value.trim()) return;
    inviteLoading.value = true;
    try {
        await inviteToPool(poolId.value, {
            email: inviteEmail.value.trim(),
            sector: inviteSector.value.trim() || null,
        });
        const sentEmail = inviteEmail.value.trim();
        inviteEmail.value = '';
        inviteSector.value = '';
        await Swal.fire({
            icon: 'success',
            title: 'Convite enviado',
            text: `Um e-mail de convite foi enviado para ${sentEmail}.`,
            timer: 2500,
            showConfirmButton: false,
            background: '#0f172a',
            color: '#e2e8f0',
        });
    } catch (err) {
        const message = err?.response?.data?.error?.message
            ?? err?.response?.data?.message
            ?? 'Não foi possível enviar o convite. Tente novamente.';
        await Swal.fire({
            icon: 'error',
            title: 'Erro ao enviar convite',
            text: message,
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        inviteLoading.value = false;
    }
}

// immediate: true garante que o watch dispara com o valor inicial de activeTab,
// evitando a race condition onde onMounted chama loadRanking() mas a primeira
// chamada falha silenciosamente e o ranking só aparece após trocar de aba.
let tabWatchFirstRun = true;
watch(activeTab, (tab) => {
    if (tab === 'ranking' && ranking.value.length === 0) loadRanking();
    if (tab === 'manage' && members.value.length === 0) loadMembers();

    // localStorage e router.replace só na mudança real, não no disparo inicial.
    if (tabWatchFirstRun) { tabWatchFirstRun = false; return; }

    localStorage.setItem(persistedDetailTabKey.value, tab);

    const currentQueryTab = String(route.query.tab || '').toLowerCase();
    if (currentQueryTab !== tab) {
        router.replace({
            path: route.path,
            query: { ...route.query, tab },
        });
    }
}, { immediate: true });

// Liga o IntersectionObserver quando o sentinel estiver disponível no DOM
watch(sentinelEl, (el) => {
    if (el) setupInfiniteScroll();
});

async function openBulkModal() {
    const { value, isConfirmed } = await Swal.fire({
        title: 'Palpite em massa',
        html: `
            <p style="font-size:13px;color:#94a3b8;margin-bottom:16px">Aplica o mesmo placar a todos os jogos abertos neste bolão.</p>
            <div style="display:flex;align-items:center;justify-content:center;gap:14px">
                <input id="bulk-home" type="number" min="0" max="99" value="0"
                    style="width:60px;text-align:center;background:#1b2230;border:1px solid rgba(255,255,255,0.2);border-radius:10px;color:#fff;font-size:30px;font-weight:800;padding:6px 0;font-family:'Barlow Condensed',sans-serif">
                <span style="color:#4a5568;font-size:26px;font-weight:800">×</span>
                <input id="bulk-away" type="number" min="0" max="99" value="0"
                    style="width:60px;text-align:center;background:#1b2230;border:1px solid rgba(255,255,255,0.2);border-radius:10px;color:#fff;font-size:30px;font-weight:800;padding:6px 0;font-family:'Barlow Condensed',sans-serif">
            </div>`,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonText: 'Aplicar a todos',
        cancelButtonText: 'Cancelar',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
        preConfirm: () => ({
            home: Math.max(0, Math.min(99, Number(document.getElementById('bulk-home')?.value ?? 0))),
            away: Math.max(0, Math.min(99, Number(document.getElementById('bulk-away')?.value ?? 0))),
        }),
    });
    if (!isConfirmed || !value) return;

    const allowChanges = !!pool.value?.allow_prediction_changes;
    const editable = predictions.value.filter(
        (i) => ['TIMED', 'SCHEDULED', 'PRE_MATCH'].includes(i.match?.status)
            && !i.lock?.is_locked
            && (allowChanges || i.prediction == null),
    );
    if (!editable.length) {
        await Swal.fire({ icon: 'info', title: 'Sem jogos disponíveis', text: 'Nenhum jogo aberto para palpite.', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
        return;
    }

    savingBulk.value = true;
    try {
        await Promise.all(editable.map((i) => savePrediction(poolId.value, i.match.id, value.home, value.away)));
        await loadPredictions();
    } catch {
        loadPredictions();
    } finally {
        savingBulk.value = false;
    }
}

async function openApplyAllModal() {
    const competitionId = pool.value?.competition?.id;
    if (!competitionId) return;

    // Apenas palpites em jogos ainda abertos e não bloqueados
    const saved = predictions.value.filter(
        (i) => ['TIMED', 'SCHEDULED', 'PRE_MATCH'].includes(i.match?.status)
            && !i.lock?.is_locked
            && i.prediction != null,
    );
    if (!saved.length) {
        await Swal.fire({ icon: 'info', title: 'Nenhum palpite disponível', text: 'Salve palpites em jogos abertos neste bolão antes de replicar.', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
        return;
    }

    const res = await getPools();
    const otherPools = (res.data.data?.member_pools ?? []).filter(
        (p) => Number(p.id) !== Number(poolId.value)
            && Number(p.competition?.id) === Number(competitionId)
            && p.membership?.status === 'active',
    );
    if (!otherPools.length) {
        await Swal.fire({ icon: 'info', title: 'Nenhum outro bolão', text: 'Você não participa de outros bolões ativos nesta competição.', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
        return;
    }

    const poolList = otherPools.map((p) => `• ${p.name}`).join('<br>');
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: 'Replicar palpites?',
        html: `Serão replicados até <strong>${saved.length} palpite(s)</strong> para:<br><br><span style="color:#94a3b8;font-size:13px">${poolList}</span><br><br><span style="color:#64748b;font-size:12px">Apenas jogos abertos (não bloqueados) em cada bolão serão atualizados.</span>`,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonText: 'Replicar',
        cancelButtonText: 'Cancelar',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
    });
    if (!isConfirmed) return;

    savingApplyAll.value = true;
    try {
        // Para cada bolão destino, carrega suas predições para saber quais jogos estão abertos
        const targetData = await Promise.all(
            otherPools.map(async (targetPool) => {
                try {
                    // open_only=1 já exclui jogos encerrados/cancelados no servidor
                    const r = await getPoolPredictions(targetPool.id, { per_page: 200, open_only: 1 });
                    const items = r.data.data?.items ?? [];
                    const editableIds = new Set(
                        items
                            .filter((i) => !i.lock?.is_locked)
                            .map((i) => Number(i.match?.id)),
                    );
                    return { pool: targetPool, editableIds };
                } catch {
                    return { pool: targetPool, editableIds: new Set() };
                }
            }),
        );

        const saves = targetData.flatMap(({ pool: targetPool, editableIds }) =>
            saved
                .filter((i) => editableIds.has(Number(i.match?.id)))
                .map((i) => savePrediction(targetPool.id, i.match.id, i.prediction.home_score, i.prediction.away_score)),
        );

        if (!saves.length) {
            await Swal.fire({ icon: 'info', title: 'Nada para replicar', text: 'Todos os jogos disponíveis já estão bloqueados nos outros bolões.', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
            return;
        }

        const results = await Promise.allSettled(saves);
        const failCount = results.filter((r) => r.status === 'rejected').length;
        const okCount = results.length - failCount;

        if (failCount === 0) {
            await Swal.fire({ icon: 'success', title: 'Replicado!', text: `${okCount} palpite(s) aplicados em ${otherPools.length} bolão(ões).`, background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b', timer: 2500, showConfirmButton: false });
        } else {
            await Swal.fire({ icon: 'warning', title: 'Replicado com avisos', text: `${okCount} palpite(s) salvos. ${failCount} não puderam ser salvos (jogos já bloqueados).`, background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
        }
    } catch {
        await Swal.fire({ icon: 'error', title: 'Erro ao replicar', text: 'Não foi possível completar a replicação.', background: '#0f172a', color: '#e2e8f0', confirmButtonColor: '#f59e0b' });
    } finally {
        savingApplyAll.value = false;
    }
}

onMounted(() => {
    loadPool();
    loadPredictions();
    // ranking e manage já carregados pelo watch imediato acima
    startClockGuard();
});

onBeforeUnmount(() => {
    if (clockGuardTimer) clearInterval(clockGuardTimer);
    if (infiniteObserver) infiniteObserver.disconnect();
});
</script>

<style scoped>
.bulk-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 10px;
    transition: border-color .15s, color .15s;
}
.bulk-action-btn:not(:disabled):active { opacity: .7; }
.bulk-action-btn:disabled { opacity: .4; }
.prediction-filter-row {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
    scrollbar-width: none;
}
.prediction-filter-row::-webkit-scrollbar {
    display: none;
}
.prediction-filter-chip {
    display: inline-flex;
    min-height: 38px;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 999px;
    background: #171d28;
    color: #94a3b8;
    padding: 8px 11px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
}
.prediction-filter-chip.compact {
    min-height: 34px;
    padding: 7px 9px;
    font-size: 11px;
}
.prediction-filter-chip strong {
    min-width: 20px;
    border-radius: 999px;
    background: rgba(255,255,255,0.07);
    padding: 3px 6px;
    text-align: center;
    color: #cbd5e1;
    font-size: 10px;
}
.prediction-filter-chip.active {
    border-color: rgba(245,166,35,0.42);
    background: rgba(245,166,35,0.13);
    color: #f5a623;
}
.prediction-filter-chip.active strong {
    background: rgba(245,166,35,0.18);
    color: #ffd084;
}
.prediction-filter-chip:disabled {
    opacity: .42;
}

/* Sticky tab bar */
.pwa-tabs-bar {
    display: flex;
    position: sticky;
    top: 0;
    z-index: 10;
    background: #0d0f12;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    padding: 0 4px;
}

.pwa-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 4px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #4a5568;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.pwa-tab-btn.active {
    color: #f5a623;
    border-bottom-color: #f5a623;
}


.card-box {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    background: #13161b;
    padding: 12px;
}
.field {
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.12);
    background: #0f131b;
    color: #e5e7eb;
    padding: 10px 11px;
    font-size: 13px;
}
.field-label {
    color: #e5e7eb;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.25;
}
.field-help {
    color: #7a8394;
    font-size: 12px;
    line-height: 1.35;
}
.toggle-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    background: #161c28;
    padding: 10px;
}
.toggle-row input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    margin-top: 1px;
    border-radius: 5px;
    border: 1px solid rgba(255,255,255,0.3);
    background: #0f131b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex: 0 0 18px;
}
.toggle-row input[type="checkbox"]::before {
    content: "";
    width: 10px;
    height: 10px;
    transform: scale(0);
    transition: transform 0.12s ease-in-out;
    clip-path: polygon(14% 52%, 0 67%, 39% 100%, 100% 23%, 85% 10%, 38% 68%);
    background: #0d0f12;
}
.toggle-row input[type="checkbox"]:checked {
    background: #f5a623;
    border-color: #f5a623;
}
.toggle-row input[type="checkbox"]:checked::before {
    transform: scale(1);
}
.toggle-row input[type="checkbox"]:focus-visible {
    outline: 2px solid rgba(245,166,35,0.45);
    outline-offset: 2px;
}
.toggle-copy {
    display: grid;
    gap: 2px;
}
.member-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    background: #171d28;
    padding: 9px 10px;
}
.mini-action {
    border: 1px solid rgba(245,166,35,.35);
    color: #f5a623;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 8px;
}
.mini-action.danger {
    border-color: rgba(239,68,68,.4);
    color: #ef4444;
}
.rank-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 8px;
    background: rgba(15, 19, 27, 0.6);
}
.rank-row.drag-active {
    opacity: 0.72;
    border-color: rgba(245, 166, 35, 0.55);
}
.rank-row.drop-target {
    border-color: rgba(245, 166, 35, 0.75);
    box-shadow: 0 0 0 1px rgba(245, 166, 35, 0.35) inset;
}
.prio-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 6px;
    color: #6ee7b7;
    background: rgba(16, 185, 129, 0.12);
}
.drag-handle {
    color: #64748b;
    letter-spacing: -1px;
    cursor: grab;
}

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.chat-rules-banner {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    border: 1px solid rgba(245,166,35,0.2);
    border-radius: 12px;
    background: rgba(245,166,35,0.06);
    padding: 10px 12px;
    margin-bottom: 2px;
}

.user-predictions-sheet {
    width: 100%;
    max-height: 86dvh;
    overflow: hidden;
    border-radius: 18px 18px 0 0;
    border: 1px solid rgba(255,255,255,0.12);
    border-bottom: 0;
    background: #0f131b;
    box-shadow: 0 -18px 48px rgba(0,0,0,0.45);
}
.user-predictions-close {
    display: flex;
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: #cbd5e1;
}
.user-prediction-row {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    background: #141a25;
    padding: 11px 12px;
}
.user-prediction-flag {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    object-fit: contain;
}
.user-prediction-score {
    min-width: 48px;
    border-radius: 10px;
    border: 1px solid rgba(245,166,35,0.28);
    background: rgba(245,166,35,0.1);
    padding: 6px 8px;
    text-align: center;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    font-weight: 900;
    line-height: 1;
    color: #f8c66d;
}
.user-prediction-points {
    border-radius: 999px;
    background: rgba(34,197,94,0.14);
    padding: 2px 6px;
    color: #86efac;
}

/* ── Info tab ──────────────────────────────────────────── */
.info-card {
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    background: #111622;
    padding: 14px 14px 16px;
}
.info-card-label {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #6b82a4;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 8px;
}
.info-card-body {
    font-size: 14px;
    color: #c8d8ef;
    line-height: 1.6;
}
.info-score-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 4px;
}
.info-score-item {
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    background: rgba(255,255,255,0.03);
    padding: 10px 6px;
    text-align: center;
}
.info-score-val {
    display: block;
    font-size: 22px;
    font-weight: 900;
    color: #f5a623;
    line-height: 1;
}
.info-score-lbl {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #6b82a4;
    margin-top: 4px;
    line-height: 1.2;
}
.info-rule-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.02);
    padding: 10px 12px;
    font-size: 13px;
    color: #c8d8ef;
}
.info-rule-row.rule-on { border-color: rgba(245,166,35,0.2); background: rgba(245,166,35,0.04); }
.invite-code-block {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 1px solid rgba(245,166,35,0.3);
    border-radius: 12px;
    background: rgba(245,166,35,0.07);
    padding: 12px 14px;
    gap: 8px;
}
.invite-code-text {
    font-size: 20px;
    font-weight: 900;
    letter-spacing: .12em;
    color: #ffd084;
}
.invite-code-copy {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #9fb8d8;
    transition: color .15s;
}
</style>

# Handoff: Paridade PWA e Administração

**Projeto Atual:** BolãoVF (bolao)
**Branch Atual:** feature/match-detail-page
**Status do Git:** Working tree clean (sem alterações pendentes)
**Último Commit:** 9be143a PWA updates

## Arquivos Criados
- `resources/js/pwa/views/ManagementView.vue`
- `resources/js/pwa/views/AdminView.vue`
- `resources/js/pwa/views/admin/AdminUsersView.vue`
- `resources/js/pwa/views/admin/AdminPoolsView.vue`
- `resources/js/pwa/views/PoolMatchDetailView.vue`
- `resources/js/pwa/store/app.js`
- `resources/js/pwa/composables/usePushNotifications.js`
- `app/Models/PushSubscription.php`
- `database/migrations/2026_05_30_032753_create_push_subscriptions_table.php`
- `app/Http/Controllers/Api/V1/Notifications/PushSubscriptionController.php`
- `app/Http/Controllers/Api/V1/Admin/AdminUserModerationController.php`
- `app/Http/Controllers/Api/V1/Admin/AdminPoolController.php`
- `app/Http/Controllers/Api/V1/Pools/PoolMatchPredictionsController.php`

## Arquivos Modificados
- `resources/js/pwa/components/layout/AppShell.vue`
- `resources/js/pwa/views/DashboardView.vue`
- `resources/js/pwa/router/index.js`
- `resources/js/pwa/store/auth.js`
- `resources/js/pwa/pwa.css`
- `resources/js/pwa/views/ProfileView.vue`
- `resources/js/pwa/api/notifications.js`
- `resources/js/pwa/components/ui/PredictionCard.vue`
- `routes/api.php`
- `app/Providers/AppServiceProvider.php`

## Objetivo da Sessão
Atingir a paridade de recursos entre a versão Web/Livewire e o PWA Vue, focando em uma experiência nativa (gestos, margens, layout flexível e notificações) e criando as ferramentas completas de Administração e Gestão de Bolões, garantindo regras de segurança estritas validadas obrigatoriamente no backend.

## O que foi implementado
1. **AppShell & Dashboard:** Navegação por gestos, pull-to-refresh vertical no dashboard (corrigido conflito de eventos de swipe horizontal através do modificador `.stop`), seletor de competição centralizado.
2. **Admin & Gestão (Frontend):** Painel (Bottom Sheet UI) para aprovar/banir/excluir usuários, gerar senha temporária. Painel para visualizar informações vitais de todos os bolões e suspendê-los se necessário.
3. **Backend & Segurança:** Gate `admin` explícito e robusto, validações de acesso `$request->user()->can('admin')` redundantes em todos os métodos da API de Admin. Proteção rigorosa contra a possibilidade de um administrador banir/deletar a si mesmo.
4. **Notificações Push:** Infraestrutura backend para gerenciar assinaturas (Migration, Controller, Model) e composable frontend (`usePushNotifications.js`) integrado de forma reativa na tela de Perfil do usuário.
5. **Detalhe de Partida no Bolão:** View isolada mostrando o andamento ao vivo e palpites detalhados de todo o grupo.

## O que foi testado
- Compilação dos componentes Vue (sem erros sintáticos).
- Lógica de roteamento do vue-router e rotas da API Laravel.
- Prevenção de "bubbling" nos touch events do Dashboard.

## O que não foi testado
- Recebimento prático fim-a-fim de notificações push via Service Worker em dispositivo móvel real.
- Simulação no banco de dados real das regras de suspensão com sessão bloqueada no front-end.

## Pendências
- Executar `php artisan migrate` para consolidar a tabela `push_subscriptions`.
- Configurar as credenciais VAPID (e.g. `VITE_VAPID_PUBLIC_KEY`) para habilitar emissões web-push.
- Validação profunda do layout em resoluções menores de iPhone antigos (SE/mini).

## Próximos passos recomendados
1. Gerar e popular as chaves VAPID no ambiente do projeto.
2. Revisar como as tabelas/listas de membros (Dashboard e Admin) reagem à limitação e paginação quando houver centenas de registros.
3. Estender o *pre-caching* offline via service worker para cobrir o detalhe de partidas e rotas de administração críticas.

## Comandos úteis para validação
```bash
php artisan migrate
npm run build && npm run dev
```

## Riscos ou pontos de atenção
- O `pull-to-refresh` via JS (`DashboardView.vue`) pode engasgar ou "brigar" nativamente com o pull-to-refresh embutido do navegador móvel (como no Safari) quando o PWA é acessado via aba (não instalado standalone). Aconselha-se monitorar isso.
- O reset de senha de usuários via painel Admin apenas retorna a senha temporária no response JSON/Alerta na tela. Em um fluxo de produção maior, o ideal seria o backend disparar um e-mail transacional automatizado após a geração.

# Dossie LGPD do Sistema - Bolao VixForge

## 1. Objetivo deste documento
Este documento consolida, em linguagem tecnico-juridica, como o sistema trata dados pessoais para suportar:
- redacao dos Termos de Uso;
- redacao da Politica de Privacidade;
- evidencias de conformidade com a LGPD;
- auditorias administrativas ou judiciais.

Escopo: aplicacao Laravel/Livewire do Bolao VixForge (web), com filas, e-mail transacional e integracoes esportivas.

## 2. Papel dos agentes de tratamento
Premissas para validacao juridica final:
- Controlador: empresa responsavel pela plataforma (preencher razao social e CNPJ).
- Operador(es): provedores de infraestrutura e comunicacao (hospedagem, banco, e-mail, cache, monitoramento), conforme contratos vigentes.
- Encarregado (DPO): definir nome, canal de contato e SLA de resposta.

## 3. Fontes de coleta de dados
Coleta direta do titular:
- cadastro publico: nome, nome de exibicao, e-mail e aceite juridico;
- login e recuperacao de senha: e-mail, eventos de autenticacao e contexto tecnico;
- edicao de perfil: nome, nome de exibicao e e-mail (com restricao para usuario comum).

Coleta indireta:
- convites para bolao enviados por outros usuarios (e-mail do convidado);
- metadados de sessao/autenticacao (IP, user-agent, tentativas, chaves de rate limit).

Coleta de terceiros:
- dados de competicoes esportivas via APIs externas (nao focado em dados pessoais de titulares da plataforma).

## 4. Bases legais (mapa inicial para validacao do juridico)
Sugestao de enquadramento por finalidade:
- Execucao de contrato (art. 7o, V): criacao de conta, autenticacao, participacao em boloes e operacao da plataforma.
- Legítimo interesse (art. 7o, IX): seguranca, prevencao de fraude/abuso, trilhas tecnicas e integridade operacional.
- Cumprimento de obrigacao legal/regulatoria (art. 7o, II): guarda de evidencias para defesa em processo e auditoria.
- Consentimento (art. 7o, I): aceite expresso dos documentos juridicos obrigatorios quando aplicavel ao desenho de onboarding.

Observacao: a base legal final deve ser fixada pelo juridico com texto definitivo na politica.

## 5. Medidas de seguranca implementadas (estado atual)
1. Senhas armazenadas com hash seguro (cast hashed no modelo de usuario).
2. Controle de tentativas de login com rate limit por identidade e IP.
3. Limites de disparo de e-mail por tipo de evento (password reset, verificacao, convites, senha temporaria).
4. Fluxo de aceite juridico com evidencia de integridade:
- hash do conteudo aceito;
- snapshot do texto aceito;
- versao aceita;
- metodo e contexto do aceite.
5. Pseudonimizacao de origem tecnica no aceite juridico:
- armazenamento de ip_hash e user_agent_hash por HMAC-SHA256;
- campos brutos ip_address e user_agent descontinuados para novos aceites.
6. Controle de imutabilidade de documento juridico publicado:
- bloqueio de edicao de campos essenciais apos publicacao;
- hash de conteudo para verificacao de integridade;
- snapshot em arquivo para trilha de auditoria.

## 6. Direitos do titular (estrutura que deve constar na politica)
A politica deve prever canal e procedimento para:
- confirmacao de tratamento;
- acesso aos dados;
- correcao de dados incompletos/inexatos;
- anonimização, bloqueio ou eliminacao (quando cabivel);
- portabilidade (quando tecnicamente viavel);
- informacao sobre compartilhamento;
- revogacao de consentimento (quando a base legal for consentimento);
- revisao de decisoes automatizadas, se houver.

Status tecnico atual:
- existe base para exportacao forense de aceites juridicos por usuario (CSV, JSON e manifest com checksum);
- ainda depende de procedimento operacional para atender requisicoes amplas de titular (playbook de atendimento LGPD).

## 7. Compartilhamento e transferencias
Compartilhamentos tecnicos observados:
- provedor de e-mail transacional para envio de mensagens da plataforma;
- provedores de API esportiva para ingestao de dados de partidas/competicoes;
- servicos de infraestrutura de banco, cache e fila.

Recomendacao juridica:
- listar operadores nominalmente na politica (ou categorias, conforme estrategia do juridico);
- registrar base legal e clausulas contratuais de protecao de dados;
- avaliar transferencia internacional, quando aplicavel.

## 8. Retencao e descarte (pendencia de governanca)
Existe trilha tecnica de dados, mas o prazo formal por categoria deve ser definido em politica de retencao.

Recomendacao de categorias para tabela de retencao:
- cadastro e conta;
- logs de autenticacao e seguranca;
- aceites juridicos e evidencias;
- convites e operacoes de bolao;
- registros de suporte/atendimento;
- backups.

## 9. Itens de conformidade ja atendidos (evidenciaveis)
- Registro de aceite juridico por usuario/documento/versao.
- Evidencia de integridade do conteudo aceito por hash + snapshot.
- Metadados de aceite (quando, como, contexto de rota/caminho).
- Pseudonimizacao de IP e user-agent no aceite juridico.
- Restricao de alteracao de e-mail para usuario comum no perfil.
- Fluxos de autenticacao com controles de abuso (rate limiting).

## 10. Gaps para fechar com juridico e governanca
- Definir texto oficial de Controlador e Encarregado (DPO).
- Aprovar matriz de bases legais por finalidade.
- Aprovar tabela de retencao e descarte.
- Definir procedimento formal para resposta a direitos do titular.
- Definir politica de incidentes e comunicacao a ANPD/titulares.
- Formalizar lista de operadores e transferencias internacionais.

## 11. Evidencias tecnicas internas (referencias)
- Aceite juridico e evidencia: app/Http/Controllers/Legal/LegalAcceptanceController.php
- Servico de evidencia do aceite: app/Services/Legal/LegalAcceptanceEvidenceService.php
- Modelo de aceite juridico: app/Models/UserLegalAcceptance.php
- Modelo e integridade de documento juridico: app/Models/LegalDocument.php
- Publicacao e snapshot juridico: app/Services/Legal/LegalDocumentPublishingService.php
- Snapshot juridico em arquivo: app/Services/Legal/LegalDocumentSnapshotService.php
- Exportacao forense de aceites: app/Services/Legal/LegalAuditExportService.php
- Comando de exportacao: app/Console/Commands/ExportLegalAuditReport.php
- Restricao de e-mail no perfil: app/Http/Requests/ProfileUpdateRequest.php
- Forca backend da restricao: app/Http/Controllers/ProfileController.php
- Tela de perfil (campo e-mail desabilitado para usuario comum): resources/views/profile/partials/update-profile-information-form.blade.php
- Estrutura base de usuario/sessao: database/migrations/0001_01_01_000000_create_users_table.php

## 12. Uso deste dossie para redacao juridica
1. Preencher itens institucionais (controlador, DPO, contatos, operadores).
2. Validar bases legais por finalidade.
3. Converter secoes 3 a 8 em clausulas de Termos e Politica.
4. Validar matriz de dados pessoais no documento 02.
5. Responder questionario do documento 03 antes da versao final juridica.

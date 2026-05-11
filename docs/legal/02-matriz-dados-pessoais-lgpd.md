# Matriz de Dados Pessoais (RoPA simplificada) - Bolao VixForge

## 1. Cadastro e conta
| Campo/Categoria | Origem | Finalidade | Base legal sugerida | Armazenamento | Compartilhamento | Retencao (definir) |
|---|---|---|---|---|---|---|
| name | Titular | Identificacao da conta | Execucao de contrato | Tabela users | Nao previsto, exceto operadores | Definir politica |
| display_name | Titular | Exibicao publica no produto | Execucao de contrato | Tabela users | Exibido a participantes conforme regra da plataforma | Definir politica |
| email | Titular | Login, comunicacoes e recuperacao | Execucao de contrato | Tabela users | Provedor de e-mail transacional | Definir politica |
| password (hash) | Titular | Autenticacao | Execucao de contrato / seguranca | Tabela users (hash) | Nao | Definir politica |
| status de conta | Sistema/Admin | Governanca de acesso | Legítimo interesse / execucao contratual | Tabela users | Nao | Definir politica |
| email_verified_at | Sistema | Estado de verificacao | Legítimo interesse / seguranca | Tabela users | Nao | Definir politica |

## 2. Perfil e uso da plataforma
| Campo/Categoria | Origem | Finalidade | Base legal sugerida | Armazenamento | Compartilhamento | Retencao (definir) |
|---|---|---|---|---|---|---|
| area/setor (quando informado) | Titular | Organizacao e experiencia de uso | Execucao de contrato | Tabela users | Nao previsto | Definir politica |
| participacao em boloes | Sistema/Usuario | Operacao principal da plataforma | Execucao de contrato | Tabelas de dominio (pools, pool_members etc.) | Participantes do mesmo bolao | Definir politica |
| convites por e-mail (PoolInvite.email) | Usuario convidante | Envio de convite | Legítimo interesse / execucao contratual | Tabela pool_invites | Provedor de e-mail transacional | Definir politica |

## 3. Seguranca e autenticacao
| Campo/Categoria | Origem | Finalidade | Base legal sugerida | Armazenamento | Compartilhamento | Retencao (definir) |
|---|---|---|---|---|---|---|
| sessao: ip_address | Coleta tecnica | Seguranca e rastreabilidade de sessao | Legítimo interesse | Tabela sessions | Nao previsto | Definir politica |
| sessao: user_agent | Coleta tecnica | Seguranca e rastreabilidade de sessao | Legítimo interesse | Tabela sessions | Nao previsto | Definir politica |
| tentativas de login (rate limiter) | Sistema | Prevencao de abuso/fraude | Legítimo interesse | Cache/Redis | Nao previsto | Janela curta operacional |
| contexto de recuperacao de senha (ip/user-agent na notificacao) | Coleta tecnica | Alerta de seguranca ao titular | Legítimo interesse | Fluxo de notificacao | Provedor de e-mail | Definir politica |

## 4. Aceite juridico
| Campo/Categoria | Origem | Finalidade | Base legal sugerida | Armazenamento | Compartilhamento | Retencao (definir) |
|---|---|---|---|---|---|---|
| user_id + legal_document_id | Sistema | Vincular titular ao documento aceito | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| accepted_at | Sistema | Prova temporal do aceite | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| acceptance_method + acceptance_context | Sistema | Prova de contexto do aceite | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| accepted_document_version | Sistema | Prova de versao aceita | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| accepted_document_hash | Sistema | Integridade do aceite | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| accepted_document_snapshot | Sistema | Conteudo exato aceito | Cumprimento legal / legitimo interesse | user_legal_acceptances | Nao previsto | Prazo juridico a definir |
| ip_hash / user_agent_hash | Coleta tecnica pseudonimizada | Correlacao tecnica sem dado bruto | Legítimo interesse / seguranca | user_legal_acceptances | Nao previsto | Prazo juridico a definir |

## 5. Integracoes e terceiros
| Integracao | Dado relacionado | Finalidade | Papel sugerido |
|---|---|---|---|
| SMTP transacional | e-mail e conteudo de notificacao | envio de comunicacoes da plataforma | Operador |
| API de dados esportivos (football-data / api-football) | dados de competicao e partidas | funcionamento do produto | Operador/Fornecedor tecnico |
| Infra (DB, Redis, fila, storage) | dados da aplicacao | processamento e disponibilidade | Operador |

## 6. Notas de conformidade
- A matriz acima deve ser homologada pelo juridico antes da publicacao da Politica de Privacidade.
- Preencher o prazo de retencao por categoria e excecoes legais.
- Validar redacao final sobre compartilhamento internacional, se houver.

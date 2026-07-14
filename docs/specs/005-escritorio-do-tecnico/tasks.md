# Tasks 005 — Escritório do Técnico

> **Plan:** ./plan.md (Aprovado em 2026-07-14)
> **Concluído em 2026-07-14** — suite completa: 58 testes verdes (42 anteriores + 16 novos).

- [x] **T1 — Migrations:** `leagues.global_round` (+backfill), `league_messages`, `league_invitations`, `league_members` (enum `fired` + colunas de contexto) · *Verificação:* `php artisan migrate` limpo no dev
- [x] **T2 — Models:** `LeagueMessage` (com `subjectUrl()`), `LeagueInvitation` (com `isOpen()`); `League` e `LeagueMember` ajustados
- [x] **T3 — `MessageService`:** `sendToTeam` (no-op para CPU), `sendToUser`, poda nas últimas 100 por user/liga
- [x] **T4 — Hooks de mensagem:** `FinancialService` (cota, salários + alerta de saldo, bilheteria), `TransferService` (proposta recebida, retenção pendente, desfechos para o comprador, venda concluída), `SatisfactionService` (demissão → member `fired` + mensagem; aviso de zona crítica threshold+5)
- [x] **T5 — `InvitationService`:** expiração por rodada, geração (divisão igual/inferior, carência ex-clube 12 rodadas, até 3/rodada), aceite transacional com `lockForUpdate`, recusa
- [x] **T6 — `GlobalRoundService`:** `global_round++` no início do advance; convites, mensagens de resultado e alerta de escalação incompleta ao final
- [x] **T7 — `OfficeController` + rotas:** index, readMessage, accept/decline; autorização liga→objeto→user em todas
- [x] **T8 — Views:** `leagues/office/index.blade.php` (inbox com filtro Alpine + convites), atalho "Escritório" com badge, redirect da home (dono fica; `?classic=1` escapa)
- [x] **T9 — Modo observador:** guards existentes cobrem ações (403); gap corrigido: `UserDashboardController` agora inclui ligas via `league_members` (demitido não perdia a liga do dashboard)
- [x] **T10 — Testes Pest:** `tests/Feature/Office/` — `OfficeMessagesTest` (4), `MessageGenerationTest` (4), `FiringAndInvitationTest` (4), `InvitationAcceptTest` (4)
- [x] **T11 — Fechamento:** suite completa verde (58), migrations aplicadas no dev, REQUISITOS.md atualizado (RF-GES-01..03 ✅, RF-SAT-05 ✅, RNF-01)

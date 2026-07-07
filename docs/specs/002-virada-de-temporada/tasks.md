# Tasks 002 — Virada de Temporada Consistente

> **Plan:** ./plan.md (Aprovado em 2026-07-06)
> **Concluído em 2026-07-07** — suite completa: 42 testes verdes.

- [x] **T1 — Migration `national_division`** em `league_teams` (enum first/second nullable) + backfill do catálogo + fillable no model
- [x] **T2 — `FinancialService::payTvQuotaFor(Competition)`** e remoção de `payTvQuotas(League)` (sem callers restantes)
- [x] **T3 — `LeagueGeneratorService`**: semear `national_division` no `attachTeams` + cota por estadual criado
- [x] **T4 — `GlobalRoundService`**: cota da Copa no `transitionToCopa`; `transitionToNational` lê `league_teams.national_division` e paga cotas
- [x] **T5 — `SeasonTransitionService`**: reset de fase, clone só de estaduais, persistência das novas divisões nacionais (`divisionMembers`), recálculo de `market_value`, cota dos estaduais novos, filtro por `season`
- [x] **T-extra — Migration `copa` no enum** de `competitions.competition_type` (gap descoberto durante a implementação: a geração da Copa falharia em banco estrito)
- [x] **T6 — Testes** `tests/Feature/SeasonTransitionTest.php` — 6 cenários verdes
- [x] **T7 — Fechamento**: suite completa verde (42), migrations aplicadas no banco de dev, docs atualizados

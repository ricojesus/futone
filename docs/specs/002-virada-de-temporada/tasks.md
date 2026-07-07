# Tasks 002 — Virada de Temporada Consistente

> **Plan:** ./plan.md (Aprovado em 2026-07-06)

- [ ] **T1 — Migration `national_division`** em `league_teams` (enum first/second nullable) + backfill do catálogo + fillable no model · *Verificação:* `php artisan migrate` limpo; coluna preenchida para times com divisão
- [ ] **T2 — `FinancialService::payTvQuotaFor(Competition)`** e remoção de `payTvQuotas(League)` · *Verificação:* grep sem callers do método antigo
- [ ] **T3 — `LeagueGeneratorService`**: semear `national_division` no `attachTeams` + cota por estadual criado · *Verificação:* gerar liga nova paga 2M/1M por estadual, uma vez por time
- [ ] **T4 — `GlobalRoundService`**: cota da Copa no `transitionToCopa`; `transitionToNational` lê `league_teams.national_division` e paga cotas · *Verificação:* teste de transição
- [ ] **T5 — `SeasonTransitionService`**: reset de fase, clone só de estaduais, persistência das novas divisões nacionais, recálculo de `market_value`, cota dos estaduais novos, filtro por `season` no `calculateTransitions` · *Verificação:* testes T6
- [ ] **T6 — Testes** `tests/Feature/SeasonTransitionTest.php` (5 cenários do plano) · *Verificação:* todos verdes
- [ ] **T7 — Fechamento**: suite completa verde; atualizar spec (status Entregue), REQUISITOS.md e review doc · *Verificação:* `php artisan test` sem falhas

# Plan 002 — Virada de Temporada Consistente

> **Status:** Aprovado (2026-07-06)
> **Spec:** ./spec.md (Aprovada em 2026-07-06)

## Decisões técnicas

1. **Fonte da verdade da divisão nacional passa a ser a liga**: nova coluna `league_teams.national_division` (`enum('first','second')`, nullable), semeada do catálogo (`teams.national_division`) na criação do `LeagueTeam` e **atualizada a cada virada** com o resultado de promoção/rebaixamento. O catálogo mestre continua intacto (é só o ponto de partida de ligas novas).
   - *Alternativa descartada:* continuar lendo de `teams.national_division` — ignora promoções da liga e vaza estado entre ligas diferentes.
2. **Um único criador de competições nacionais**: `GlobalRoundService::transitionToNational` (na transição pós-Copa), agora lendo `league_teams.national_division`. `SeasonTransitionService::advanceSeason` **deixa de clonar** as nacionais — clona apenas os pares estaduais.
   - *Alternativa descartada:* clonar tudo na virada com status `waiting` — manteria dois caminhos de criação e a Copa continuaria fora do padrão.
3. **Cota de TV por competição no momento da criação** (decisão da spec): novo `FinancialService::payTvQuotaFor(Competition)`; `payTvQuotas(League)` é removido (código morto após o refactor, Art. VI.3).
4. **Valor de mercado recalculado na virada**, logo após o `increment('age')`, em chunk — sem tocar no `PlayerDevelopmentService` (fica para decisão futura com a spec 001).
5. **Satisfação intocada na virada** (decisão da spec: carrega da temporada anterior) — o plano não introduz nenhum reset.

## Modelo de dados

Migration `add_national_division_to_league_teams`:
- `league_teams.national_division` `enum('first','second')` nullable, after `team_id`.
- Backfill: `UPDATE league_teams JOIN teams ... SET league_teams.national_division = teams.national_division WHERE teams.national_division IN ('first','second')` — corrige ligas em andamento na 1ª temporada.

## Componentes afetados

| Camada | Arquivo | Mudança |
|---|---|---|
| Migration | `2026_07_XX_add_national_division_to_league_teams.php` | coluna + backfill |
| Model | `LeagueTeam` | `national_division` no fillable |
| Service | `FinancialService` | + `payTvQuotaFor(Competition)`; − `payTvQuotas(League)` |
| Service | `LeagueGeneratorService` | semear `national_division` no `attachTeams`; pagar cota por estadual criado (substitui a chamada global no fim do `generate`) |
| Service | `GlobalRoundService` | `transitionToCopa`: pagar cota da Copa após `generate`; `transitionToNational`: ler `league_teams.national_division` + pagar cota das Séries criadas |
| Service | `SeasonTransitionService` | `advanceSeason`: reset `current_phase = state`; clonar só estaduais; persistir novas divisões nacionais nos `LeagueTeam`s; recalcular `market_value` pós-envelhecimento; pagar cota dos estaduais novos; remover chamada a `payTvQuotas`. `calculateTransitions`: filtrar `season = league->season` |

## Pontos de integração

- `LeagueController::advanceSeason` e `seasonSummary` não mudam (consomem os mesmos retornos).
- A Copa da temporada N+1 continua sendo gerada por `transitionToCopa` — que agora também paga a cota (adiantando essa parte do achado A1; o restante da Copa fica na spec 003).
- `MarketValueService` é injetado no `SeasonTransitionService` (cálculo já existente, só passa a ser chamado).

## Estratégia de testes

`tests/Feature/SeasonTransitionTest.php`:
1. **Fase reseta**: liga em `national` com tudo `finished` → `advanceSeason` → `current_phase = state`; apenas estaduais N+1 `in_progress`; **nenhuma** Série A/B N+1 existe ainda.
2. **Sem clone nacional / sem duplicata**: após a virada, transição pós-Copa cria Série A/B N+1 exatamente uma vez.
3. **Promoção vale**: time campeão da Série B (N) tem `national_division = first` após a virada e entra na Série A criada na fase nacional de N+1.
4. **Filtro de temporada**: competição antiga (N−1) com pontuações altas não contamina `calculateTransitions` de N.
5. **Economia**: cota paga na criação (estadual, Copa, nacional) com transação por time; `market_value` recai após envelhecer (ex.: 31→32 anos); satisfação do time preservada na virada.

## Riscos e mitigação

- **Ligas já quebradas na 2ª temporada** (dados criados pelo fluxo antigo): o plano não repara dados históricos — usar `php artisan league:reset` nessas ligas. Ligas na 1ª temporada são cobertas pelo backfill.
- **Remoção de `payTvQuotas`**: verificar (grep) que nenhum caller sobra antes de apagar.
- **Copa com cota paga 2×** (criação + eventual chamada antiga): eliminado porque a chamada global deixa de existir.

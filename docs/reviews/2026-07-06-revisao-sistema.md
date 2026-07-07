# Revisão do Sistema — 2026-07-06

Revisão completa de lógica, implementação e UX. Cada achado tem severidade, local exato e correção proposta.

> **Status (2026-07-06, após aprovação):** **Lote 1 ENTREGUE** — C2, C3, C4, A2, A4, M1, M2, M3, M6, M7, M8, M9, M10, V3 e V4 corrigidos, com 11 testes Pest novos (suite completa: 36 verdes, MySQL `futone_testing`).
> Os testes revelaram **2 bugs extras**, também corrigidos:
> - **Enum sem `free_agent`**: `competition_players.status` não aceitava o valor usado pelo código — migration `2026_07_06_000001` adicionada.
> - **Overflow de saldo negativo**: `goals_for - goals_against` com colunas UNSIGNED estoura no MySQL quando o saldo é negativo (crash latente no antigo `tableRank`) — resolvido com CAST no `scopeStandingsOrder`.
> E confirmaram a gravidade de **A5**: `joined_at` tem `useCurrent()`, então TODO jogador nasce dentro dos "6 meses reais" de contrato mínimo — o mercado fica quase todo bloqueado até a spec 004.
> Pendentes: C1 (spec 002), A1+A3+M4+M5 (spec 003), A5+A6 (specs 002/004), V1, V2.

---

## 🔴 Críticos — quebram o jogo ou corrompem dados

### C1. A 2ª temporada não funciona (fase nunca volta para `state`)
**Onde:** `SeasonTransitionService::advanceSeason` + `GlobalRoundService`
`current_phase` só é escrito em três lugares: geração inicial (`state`), transição para copa e transição para nacional. `advanceSeason` **não reseta a fase**. Ao virar o ano, a liga continua em `national` — e como `createNewSeasonPair` já clona Série A/B da nova temporada com `status = in_progress`, o próximo `advanceWeek` sai jogando o Brasileirão novo direto. Os estaduais clonados nunca são jogados e a Copa nunca é gerada.
Agravantes:
- Se a fase fosse resetada, haveria **duplicação**: `transitionToNational` criaria uma segunda Série A/B (a partir do `Team::national_division` do catálogo mestre, ignorando promoções/rebaixamentos da liga), conflitando com as clonadas. Existem dois criadores concorrentes de competições nacionais.
- `calculateTransitions` carrega `$league->competitions()` **sem filtrar por `season`** — a partir da 2ª temporada mistura competições de anos anteriores no cálculo.
**Correção proposta:** resetar `current_phase = state` no `advanceSeason`; clonar nacionais com status `waiting` (ou não clonar — deixar `transitionToNational` criar usando as divisões calculadas pela transição, persistidas no `LeagueTeam`); filtrar `calculateTransitions` por temporada. Merece **spec própria** (mexe no fluxo central).

### C2. Mercado de transferências vaza entre ligas
**Onde:** `TransferController::index/show/store`
A busca é `CompetitionPlayer::where('status','active')->where('league_team_id','!=', $meuTime)` — **sem escopo de liga**. Jogadores de todas as ligas do servidor aparecem na listagem, e `store` valida apenas `exists:competition_players,id`. Um técnico pode comprar um jogador de outra sala e movê-lo para a sua, corrompendo os dois mundos.
**Correção proposta:** `whereHas('leagueTeam', fn($q) => $q->where('league_id', $league->id))` no index e `abort_unless($player->leagueTeam?->league_id === $league->id, 404)` em show/store/respond.

### C3. Contra-proposta de retenção: o jogador **nunca** sai
**Onde:** `TransferService::retentionOffer` (linhas 177–195)
O salário do jogador é atualizado para `retentionWage` **antes** da decisão; em seguida `minimumWage()` retorna `novo_salário × 1.15`, e a condição de saída exige `retentionWage >= retentionWage × 1.15` — matematicamente impossível. Resultado: toda contra-proposta termina em "jogador prefere ficar", e o aumento salarial fica gravado. O comprador nunca leva o jogador após um counter, e o vendedor pode "pagar" qualquer valor simbólico para reter.
**Correção proposta:** implementar a regra aprovada no design ([[project-financeiro]]): reavaliar `playerScore` com o novo salário e aceitar ficar se pontuação < 4; só persistir o novo salário se o jogador ficar.

### C4. Jogador vendido continua na escalação do vendedor
**Onde:** `TransferService::executeTransfer`
A transferência move o `CompetitionPlayer` de time, mas **não remove o registro em `competition_lineup_players`** do vendedor. Se era titular, a lineup do vendedor segue escalando um jogador que agora pertence a outro clube (a simulação carrega a lineup persistida). O jogador pode atuar pelos dois times na mesma rodada.
**Correção proposta:** dentro da transação, deletar os registros de lineup do jogador nos times ≠ comprador (e o buraco na lineup do vendedor deve ser sinalizado na UI de escalação).

---

## 🟠 Altos — regras erradas ou dinheiro sumindo

### A1. Cota de TV: Copa nunca paga; Série A/B sem cota na 1ª temporada
**Onde:** `FinancialService::payTvQuotas` — chamado só em `LeagueGeneratorService::generate` e `SeasonTransitionService::advanceSeason`. Nesses dois momentos, a Copa não existe (é criada em `transitionToCopa`) e, na 1ª temporada, Série A/B também não (criadas em `transitionToNational`). O design aprovado previa: time da Série A + Copa + A1 recebe 17M; hoje recebe 2M na temporada 1.
**Correção proposta:** pagar a cota da competição no momento em que ela é criada (gancho em `CopaBrasilService::generate` e `transitionToNational`), ou pagar tudo antecipado consultando divisão/classificação prevista.

### A2. Classificação ordenada pelo critério errado (padrão repetido)
**Onde:** `CompetitionController::show:54-57` e `CopaBrasilService::collectParticipants:202-205`
`->sortByDesc('points')->sortByDesc('wins')` em Collections do Laravel: **o último sort domina** (sort estável). A tabela exibida ordena primariamente por vitórias, não por pontos; na Copa, a seleção de participantes ordena primariamente por **saldo de gols** — um "campeão estadual" pode ser escolhido errado. `SeasonTransitionService::standings()` faz certo (callback com array de critérios).
**Correção proposta:** usar o mesmo callback de array em ambos (extrair para um helper único, ex.: `CompetitionTeam::scopeStandingsOrder` ou método no model) — pontos → vitórias → saldo → gols pró.

### A3. Copa do Brasil é um subsistema de segunda classe
**Onde:** `CopaBrasilService::advanceRound` + `GlobalRoundService::advance`
Na fase copa: (a) partidas de times **humanos são simuladas como CPU** — sem 1º tempo ao vivo, sem substituições; (b) **sem bilheteria/público** (o peso 1.0 da Copa existe no `FinancialService` mas nunca é usado); (c) **satisfação não varia** (`roundsCompleted` só é populado fora da copa); (d) **artilharia não conta** os gols; (e) **fitness não degrada nem recupera**; (f) `winner_team_id` nunca é gravado; (g) empate no agregado é decidido por seed, silenciosamente (sem pênaltis); (h) bracket com número ímpar de times **descarta um time** (`while (left < right)` não pareia o do meio).
**Correção proposta:** merece **spec própria** ("Copa de primeira classe"): reusar o pipeline do `CompetitionRoundService` (revenue, satisfação, gols, fitness, ao vivo) e adicionar decisão por pênaltis simulados.

### A4. Salários somem sem registro no extrato
**Onde:** `FinancialService::deductWeeklySalaries`
Debita o `budget` mas não cria `CompetitionTransaction`. Cota, bilheteria e transferências geram registro; salário não. O extrato nunca vai fechar com o saldo.
**Correção proposta:** criar transação `type = 'wages'` por semana com o total (e descrição com nº de jogadores).

### A5. Contratos usam tempo real, não o calendário do jogo
**Onde:** `TransferService::isInMinimumContract` (`now() + 6 meses` reais) e `executeTransfer` (`contract_until = date('Y') + anos` reais)
O jogo avança por rodadas — uma temporada inteira pode ser jogada numa noite. Na prática: jogador comprado fica **intransferível por 6 meses do mundo real**, e `contract_until` compara ano real com temporada do jogo (que o usuário define livremente, ex.: 2030).
**Correção proposta:** medir contrato em rodadas/temporadas do jogo (ex.: `joined_at_round` + `league.season`); casa com a spec futura de expiração de contratos (RF-TRF-09).

### A6. Valor de mercado congelado para sempre
**Onde:** `MarketValueService::refresh` e `PlayerDevelopmentService` — **nenhum dos dois é chamado por qualquer fluxo**. O comentário diz "chamado ao final de cada rodada pelo game engine", mas não é. Jogadores envelhecem (+1/temporada) e o `market_value` continua o do seed; a curva de idade só afeta quem entra novo.
**Correção proposta:** chamar `refresh` em lote na virada de temporada (após o `increment('age')`) e/ou após rodadas; decidir destino do `PlayerDevelopmentService` (religar ou remover). Conecta com a spec 001 (forma) e RF-CON-04.

---

## 🟡 Médios — comportamento incorreto com impacto limitado

| # | Achado | Onde | Proposta |
|---|---|---|---|
| M1 | Desgaste de fitness pós-jogo CPU atinge o **elenco inteiro** dos dois times (banco e fora do jogo inclusive), não os 11+subs | `CompetitionRoundService::applyFitnessDegradation` | Degradar só os titulares da lineup usada (como já faz o fluxo ao vivo) |
| M2 | Substituições da CPU no intervalo **mutam a lineup padrão permanentemente** (round 0), em vez de criar override da rodada como faz o `MatchController::applySubstitutions` | `CompetitionRoundService::applyCpuHalftimeSubstitutions` | Clonar override para a rodada antes de trocar |
| M3 | Desgaste do 2º tempo ao vivo lê a lineup errada: usa `current_round` (ainda não incrementado) em vez de `match->round` — ignora o override com as substituições | `MatchController::applyFitnessDegradation:500` | Passar `$match->round` |
| M4 | Satisfação não varia na fase Copa (times jogam mas torcida não reage) | `GlobalRoundService::advance` | Incluir partidas de copa no `updateAfterRound` (faz parte da spec A3) |
| M5 | Artilharia da página da competição soma gols de **todas** as competições (goals_scored é contador global do jogador) | `CompetitionController::show:61` | Contar gols por competição (extrair dos eventos ou tabela própria) — pode entrar na spec da Copa |
| M6 | `avgStrength`: `(float) $avg ?? 50.0` — o cast precede o `??`, elenco vazio vira 0.0 (nunca 50), distorcendo a força relativa | `SatisfactionService::avgStrength:218` | Parênteses: `(float) ($x ?? 50.0)` |
| M7 | Vendedor CPU sem elenco mínimo → `abort(422)` estoura na cara do **comprador**, em vez de rejeitar a oferta | `TransferService::resolveTeamDecision:133` | Trocar abort por `status = rejected_team` |
| M8 | Free agents provavelmente invisíveis no mercado: `where('league_team_id','!=',$id)` exclui `NULL` em SQL | `TransferController::index:39` | `orWhereNull` / filtro explícito; verificar como free agents são armazenados |
| M9 | CPU faz no máx. 3 substituições no intervalo; humano pode 5 | `CompetitionRoundService:316` | Unificar em 5 (constante compartilhada) |
| M10 | `LiveMatchSimulator` instancia dependências com `new` no construtor (`new FinancialService()`), fugindo do container | `LiveMatchSimulator:25-27` | Injeção normal via container |

---

## 🔵 Visual / UX — propostas (não são bugs)

| # | Proposta | Motivação |
|---|---|---|
| V1 | **Tela de extrato financeiro** do time (histórico de `CompetitionTransaction` com filtro por tipo/rodada) | Os dados já existem; hoje o técnico vê o saldo mudar sem saber por quê (agrava com A4) |
| V2 | **Indicador de ofertas pendentes** (badge no menu/nav) | Contra-propostas só são descobertas se o técnico visitar a tela de ofertas — em multiplayer isso trava negociações |
| V3 | **Aviso de saldo negativo** no painel financeiro (enquanto a regra RF-FIN-08 não é definida) | Hoje o budget fica negativo silenciosamente |
| V4 | Na tela de escalação, **alertar buraco na lineup** (menos de 11 titulares válidos — ex.: pós-venda, ver C4) | Evita escalar time incompleto sem perceber |
| V5 | Público/renda no card da partida da **agenda** (não só na tela da partida) | Feedback econômico por rodada com custo baixo |

---

## Plano sugerido (aguardando sua aprovação)

**Lote 1 — Hotfixes pontuais, sem spec (correções pequenas e inequívocas):**
C2 (escopo de liga no mercado), C3 (retenção), C4 (lineup do vendido), A2 (ordenação), A4 (transação de salário), M3, M6, M7, M8, M10 — com testes Pest cobrindo cada um (inaugura o RNF-01).

**Lote 2 — Spec `002-temporada-2` (C1 + A6 parcial):**
Redesenhar a virada de temporada: reset de fase, quem cria as competições nacionais, filtro por season, refresh de market_value no envelhecimento.

**Lote 3 — Spec `003-copa-primeira-classe` (A3 + M4 + M5 + A1 parcial):**
Copa com ao vivo, bilheteria, satisfação, artilharia, fitness, pênaltis e cota de TV.

**Lote 4 — Spec `004-tempo-de-jogo` (A5):**
Contratos e prazos medidos em rodadas/temporadas do jogo (prepara RF-TRF-09).

M1/M2/M9 podem entrar no lote 1 (comportamento claro) ou junto da spec 001 (forma/fitness) — sua escolha.

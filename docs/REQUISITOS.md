# Futone — Documento de Requisitos

> **Versão:** 1.1 — 2026-07-06
> **Processo:** Spec-Driven Development (ver [constitution.md](constitution.md), Artigo I). Este documento é o **inventário e backlog**: toda feature nova nasce aqui como requisito (`💡 Proposto`). Para ser implementado, um requisito ganha uma spec em [specs/](specs/) (`NNN-nome/spec.md` → `plan.md` → `tasks.md`, com aprovação a cada etapa). Ao entregar, atualizar o status aqui com link para a spec.

**Legenda de status:**

| Status | Significado |
|---|---|
| ✅ | Implementado e integrado ao fluxo do jogo |
| ⚠️ | Parcial — estrutura existe, mas falta integração, UI ou calibração |
| ❌ | Aprovado, mas não implementado |
| 💡 | Proposto — aguardando discussão/aprovação |

**Convenção de IDs:** `RF-<MÓDULO>-<NN>` para requisitos funcionais, `RNF-<NN>` para não funcionais.

---

## 1. Contas e Acesso (ACC)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-ACC-01 | Registro, login, recuperação de senha e perfil de usuário | ✅ | Laravel Breeze, `ProfileController` |
| RF-ACC-02 | Dois papéis: `administrador` (dados mestres) e `padrão` (jogador) | ✅ | middleware `admin`, `User.type` |
| RF-ACC-03 | Dashboard do usuário com suas ligas (filtro: todas / criadas / participando) | ✅ | `UserDashboardController` |

## 2. Dados Mestres (ADM)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-ADM-01 | CRUD de times, jogadores, técnicos, campeonatos, países e estados | ✅ | `app/Http/Controllers/Admin/*` |
| RF-ADM-02 | Importação em massa via CSV (times, jogadores, etc.) com templates | ✅ | `storage/app/csv-templates/`, actions `upload` |
| RF-ADM-03 | Upload em lote de escudos, vinculados por nome do arquivo | ✅ | `TeamController::uploadLogos` |
| RF-ADM-04 | Base real: 27 estados, 221+ times com divisão estadual (A1/A2) e nacional (A/B/nenhuma) | ✅ | `BrazilianStatesSeeder`, `BrazilianTeamsSeeder` |

## 3. Liga e Lobby (LIG)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-LIG-01 | Criar liga (mundo de jogo) com código de convite para entrada de outros usuários | ✅ | `LeagueController`, `LeagueJoinController` |
| RF-LIG-02 | Atribuição de times **manual** (jogador escolhe) ou **automática** (sorteio pelo dono) | ✅ | `LeagueTeamController`, `LeagueLobbyController` |
| RF-LIG-03 | Duração configurável em temporadas (`max_seasons`); liga encerra automaticamente na última | ✅ | `League::isLastSeason()`, `SeasonTransitionService` |
| RF-LIG-04 | Geração das competições da temporada: estaduais A1/A2 (27 estados), Copa do Brasil, Série A/B | ✅ | `LeagueGeneratorService`, `CopaBrasilService` |
| RF-LIG-05 | Fases sequenciais da temporada: `state → copa → national`, com transição automática | ✅ | `GlobalRoundService::transitionPhase()` |
| RF-LIG-06 | Avanço de rodada global pelo dono da liga (`advanceWeek`) simula todas as partidas da fase | ✅ | `GlobalRoundService` |
| RF-LIG-07 | Promoção/rebaixamento entre A1↔A2 e Série A↔Série B na virada de temporada | ✅ | `SeasonTransitionService`; divisão nacional persistida em `league_teams.national_division`. **Spec:** [002](specs/002-virada-de-temporada/spec.md) (entregue 2026-07-07) |
| RF-LIG-08 | Resumo de temporada (campeões, artilheiros) antes de avançar o ano | ✅ | `LeagueController::seasonSummary` |

## 4. Elenco e Escalação (ESC)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-ESC-01 | Escalação com 8 formações táticas; somente os 11 titulares são persistidos | ✅ | `LineupController`, `competition_lineup_players` |
| RF-ESC-02 | Reservas derivados dinamicamente: elenco ativo menos titulares | ✅ | convenção documentada no CLAUDE.md |
| RF-ESC-03 | Tela do time: elenco unificado, OVR, fitness, satisfação, painel financeiro | ✅ | `leagues/teams/show.blade.php` |
| RF-ESC-04 | Scouting do adversário acessível pela tabela de classificação | ✅ | commit `b74dab0` |

## 5. Partidas (PAR)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-PAR-01 | Simulação instantânea CPU×CPU (90 min tick a tick, narração de eventos) | ✅ | `MatchSimulator`, `MatchEngine`, `MatchNarrator` |
| RF-PAR-02 | Partida ao vivo em dois tempos: 1º tempo salvo em `MatchState`, intervalo interativo, 2º tempo finaliza | ✅ | `LiveMatchSimulator`, `MatchController` |
| RF-PAR-03 | Até 5 substituições no intervalo; reserva ganha registro na lineup se não existir | ✅ | `matches/halftime.blade.php` |
| RF-PAR-04 | Coordenação de intervalo Humano×Humano com timeout de 1 minuto | ✅ | commit `df4282e` |
| RF-PAR-05 | Fator casa no motor de partida (+3 de força) | ✅ | `MatchEngine` |
| RF-PAR-06 | Replay completo da partida com escudos, placar e narração | ✅ | `matches/show.blade.php` |
| RF-PAR-07 | Público e bilheteria exibidos na tela da partida | ✅ | commit `914471c` |
| RF-PAR-08 | Copa do Brasil: mata-mata ida/volta com bracket e nomes de fase | ✅ | `CopaBrasilService::advanceRound` |

## 6. Condição do Jogador (CON)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-CON-01 | Fitness degrada ao jogar e recupera em rodadas de descanso; afeta a força na simulação | ✅ | `CompetitionRoundService` (inline), `SimulatesMatch` |
| RF-CON-02 | Auto-substituição de titulares com fitness < 55 em times CPU | ✅ | `CompetitionRoundService` |
| RF-CON-03 | Forma (`form_factor`) evoluindo dinamicamente por desempenho, com decaimento | ⚠️ | `FormService` existe mas **usa models extintos e não é chamado** — `form_factor` fica travado em 1.0. **Spec:** [001-forma-dinamica](specs/001-forma-dinamica/spec.md) (rascunho) |
| RF-CON-04 | Evolução/regressão de atributos por idade e potencial na virada de temporada | ⚠️ | `PlayerDevelopmentService` existe mas **não é invocado por nenhum fluxo** |
| RF-CON-05 | Lesões dinâmicas (risco por fadiga, `injured_until`, indisponibilidade) | ❌ | campos existem em `competition_players`; lógica só no `StaminaService` morto |

## 7. Satisfação e Técnicos (SAT)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-SAT-01 | Satisfação 1–100, varia apenas quando o time joga | ✅ | `SatisfactionService` |
| RF-SAT-02 | Tabela de variação casa/fora × força relativa do adversário (aprovada em 2026-06-08) | ✅ | `SatisfactionService::DELTAS` — valores a calibrar em testes |
| RF-SAT-03 | Demissão automática de técnico quando satisfação cruza a tolerância; time humano vira CPU | ✅ | `SatisfactionService::checkFirings` |
| RF-SAT-04 | Pool de técnicos da liga (free agents) alimentado por demissões | ✅ | `LeagueCoach`, `releaseCoachToPool` |
| RF-SAT-05 | Notificar/convidar usuário demitido para assumir outro time | ❌ | TODO em `SatisfactionService:239` |
| RF-SAT-06 | UI de contratação de técnico do pool | ❌ | sem controller/rota |

## 8. Financeiro (FIN)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-FIN-01 | Saldo inicial por divisão: 15M (1ª) / 7,5M (2ª) | ✅ | `LeagueGeneratorService` |
| RF-FIN-02 | Cota de TV no início da temporada, por competição (Série A 10M, Copa 5M, Série B 4M, A1 2M, A2 1M) | ✅ | `FinancialService::payTvQuotas` |
| RF-FIN-03 | Salários semanais debitados a cada `advanceWeek` | ✅ | `FinancialService::deductWeeklySalaries` |
| RF-FIN-04 | Capacidade de estádio por time; renda de bilheteria só para o mandante | ✅ | `FinancialService::processMatchRevenue` |
| RF-FIN-05 | Público = f(capacidade, satisfação, peso da competição, preço do ingresso) | ✅ | `FinancialService::calculateAndStoreAttendance` |
| RF-FIN-06 | Técnico ajusta o preço do ingresso | ✅ | `LeagueTeamController::updateTicketPrice` |
| RF-FIN-07 | Extrato de transações (`CompetitionTransaction`) com tipo, valor e rodada | ✅ | gerado pelo `FinancialService` |
| RF-FIN-08 | Comportamento em saldo negativo (bloqueio de compras? penalidade? falência?) | 💡 | **regra nunca definida — hoje o saldo pode ficar negativo sem consequência** |
| RF-FIN-09 | Tela de extrato financeiro (histórico de transações navegável pelo técnico) | 💡 | dados existem; sem view dedicada |

## 9. Mercado de Transferências (TRF)

| ID | Requisito | Status | Rastreabilidade |
|---|---|---|---|
| RF-TRF-01 | Mercado sempre aberto; pesquisa de jogadores com filtros (posição, idade, valor, overall) | ✅ | `TransferController::index`, `transfers/index.blade.php` |
| RF-TRF-02 | Proposta direta: valor de transferência + salário; validação de saldo do comprador | ✅ | `TransferService::makeDirectOffer` |
| RF-TRF-03 | IA de aceitação com sistema de pontuação (salário, divisão, tabela, idade, titularidade) | ✅ | `TransferService::resolvePlayerDecision` — pesos a calibrar |
| RF-TRF-04 | Contra-proposta de retenção salarial pelo técnico humano vendedor | ✅ | `TransferService::retentionOffer`, `transfers/offers.blade.php` |
| RF-TRF-05 | Limites de elenco: mín. 15 jogadores, mín. 2 goleiros, máx. 25 | ✅ | `TransferService::canSell/canBuy` |
| RF-TRF-06 | Contrato mínimo de 6 meses antes de nova negociação | ✅ | `TransferService::isInMinimumContract` |
| RF-TRF-07 | Contratação de free agents (salário mínimo por valor de mercado × expectativa) | ✅ | `TransferService::signFreeAgent` |
| RF-TRF-08 | Valor de mercado dinâmico (overall, idade — declínio aos 28, forma) | ✅ | `MarketValueService` |
| RF-TRF-09 | Expiração de contrato: jogador vira free agent ao fim de `contract_until`; renovação pelo técnico | ❌ | campo existe; sem lógica de expiração nem UI de renovação |
| RF-TRF-10 | Times CPU ativos no mercado (comprar reforços, vender excedentes) | 💡 | hoje só a IA de *aceitação* existe; CPU nunca inicia negociação |
| RF-TRF-11 | Impacto de lesão no valor de mercado | ❌ | previsto no design financeiro, adiado junto com RF-CON-05 |

## 10. Requisitos Não Funcionais (RNF)

| ID | Requisito | Status | Observação |
|---|---|---|---|
| RNF-01 | Cobertura de testes Pest para services de regra de negócio (financeiro, transferências, satisfação, temporada) | ⚠️ | Infra pronta (MySQL `futone_testing`, helpers em `tests/Pest.php`) + 11 testes de transferências/financeiro/classificação (2026-07-06). Falta: satisfação, temporada, partida |
| RNF-02 | Autorização consistente: validar cadeia `league → competition → match` e posse do time em toda action | ⚠️ | convenção existe; mercado ganhou escopo de liga + testes (2026-07-06); demais áreas sem verificação sistemática |
| RNF-03 | Escritas multi-tabela sempre em `DB::transaction()` | ⚠️ | convenção documentada; auditar pontos críticos (transferências, avanço de rodada) |
| RNF-04 | Tema dark consistente (slate + emerald para o time do usuário), interatividade via Alpine.js | ✅ | convenções no CLAUDE.md |
| RNF-05 | Remoção de código morto (services órfãos que referenciam models extintos) | ❌ | `StaminaService`, `FormService` (models `LeaguePlayer`/`LeagueMatch` não existem mais) |
| RNF-06 | Valores de balanceamento centralizados e calibráveis (satisfação, público, IA de transferência, cotas) | 💡 | hoje espalhados em constantes por service; considerar config única de game design |

---

## Backlog Proposto (para discussão)

Ordem sugerida considerando risco × valor:

1. **RNF-01 — Testes dos services críticos.** O jogo já tem economia e mercado funcionando sem nenhum teste; qualquer refactor agora é voo cego. Sugestão: começar por `FinancialService`, `TransferService` e `SeasonTransitionService`.
2. **RNF-05 — Limpeza de código morto.** Deletar `StaminaService` e `FormService` (ou reescrevê-los para a arquitetura atual — ver item 3). Decidir o destino do `PlayerDevelopmentService` (integrar ou remover).
3. **RF-CON-03 + RF-CON-04 — Forma dinâmica e evolução de jogadores.** Estrutura já existe (`form_factor` no cálculo de força, service de evolução pronto); falta religar à arquitetura `CompetitionPlayer`. Alto valor de gameplay por custo baixo.
4. **RF-TRF-09 — Ciclo de contratos.** Expiração + renovação fecha o loop do mercado; sem isso `contract_until` é cosmético.
5. **RF-TRF-10 — CPU ativa no mercado.** Dá vida à economia: hoje o mercado só se move quando um humano age.
6. **RF-CON-05 — Lesões dinâmicas.** Profundidade tática (força rotação de elenco); depende de 3 para o risco por fadiga fazer sentido.
7. **RF-SAT-05 + RF-SAT-06 — Ciclo de demissão/recontratação de técnicos.** Completa o loop multiplayer de demissões.
8. **RF-FIN-08 — Regra de saldo negativo.** Decisão de game design pendente; sem ela a economia não tem pressão real.

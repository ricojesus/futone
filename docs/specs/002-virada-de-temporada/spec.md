# Spec 002 — Virada de Temporada Consistente

> **Status:** ✅ Entregue (2026-07-07) — ver ./tasks.md
> **Origem:** Revisão 2026-07-06 — achados C1 (crítico), A6 e parte de A1
> **Criada em:** 2026-07-06

## Resumo

Hoje a liga só funciona por completo na 1ª temporada. Na virada de ano a fase não volta para `state`, as Séries A/B da temporada nova já nascem "em andamento" e existem dois criadores concorrentes de competições nacionais (o clone da virada e a transição pós-Copa, que usa as divisões do catálogo mestre e ignora promoções da liga). Esta spec redesenha a virada para que toda temporada repita o ciclo estadual → Copa → nacional com as divisões corretas, e aproveita o momento para religar o envelhecimento econômico (valor de mercado).

## Escopo

- Reset do ciclo de fases na virada (`current_phase = state`).
- Um único dono da criação de competições nacionais, respeitando promoção/rebaixamento da liga.
- Divisão nacional do clube persistida na liga (não mais lida do catálogo mestre a cada transição).
- `calculateTransitions` restrito à temporada atual.
- Recalcular `market_value` de todos os jogadores na virada (após `age + 1`).
- Cota de TV paga no momento em que cada competição é criada (elimina o gap da temporada 1).

### Fora de escopo

- Evolução de atributos (`PlayerDevelopmentService`) — depende da spec 001 e de decisão sobre o service.
- Copa do Brasil (spec 003).
- Contratos em tempo de jogo (spec 004).

## User Stories

### US-1 — Toda temporada tem ciclo completo

**Como** jogador, **quero** que a 2ª temporada (e seguintes) comece nos estaduais e passe por Copa e nacional, **para** que o jogo seja jogável além do 1º ano.

**Critérios de aceitação:**
- [ ] **Dado** o fim da temporada N, **quando** o dono avança a temporada, **então** `current_phase = state` e apenas os estaduais N+1 estão `in_progress`.
- [ ] **Dado** a temporada N+1 em fase `state`, **quando** todos os estaduais terminam, **então** a Copa N+1 é gerada normalmente.
- [ ] **Dado** a fase copa concluída, **então** Série A/B N+1 são criadas **uma única vez** (sem duplicatas).

### US-2 — Promoção e rebaixamento valem de verdade

**Como** técnico que subiu para a Série A, **quero** disputar a Série A na temporada seguinte, **para** que a conquista tenha efeito.

**Critérios de aceitação:**
- [ ] **Dado** um time promovido da Série B, **quando** a Série A da nova temporada é criada, **então** ele está nela (e o rebaixado da A está na B).
- [ ] **Dado** promovidos/rebaixados dos estaduais (A1↔A2), **então** as edições novas refletem as trocas.
- [ ] A divisão nacional vigente do clube fica registrada no `LeagueTeam` (fonte da verdade da liga), atualizada a cada virada.

### US-3 — Resumo da temporada correto em qualquer ano

**Critérios de aceitação:**
- [ ] **Dado** a liga na temporada N+1, **quando** o resumo/cálculo de transições roda, **então** só competições da temporada N+1 entram no cálculo.

### US-4 — Economia acompanha o envelhecimento

**Critérios de aceitação:**
- [ ] **Dado** a virada de temporada, **quando** os jogadores envelhecem +1 ano, **então** o `market_value` de todos é recalculado pela curva de idade.
- [ ] **Dado** a criação de qualquer competição (estadual, Copa, Série A/B), **então** a cota de TV daquela competição é paga aos participantes naquele momento, com transação no extrato.

## Casos de borda

- **Liga na última temporada** (`isLastSeason`): encerra sem gerar competições novas (comportamento atual preservado).
- **Time sem divisão nacional** (clube pequeno): continua fora das Séries A/B, participando só do estadual.
- **Estadual A2 inexistente em algum estado** (menos de 2 times): virada não pode quebrar — sem rebaixados naquele estado.

## Decisões (aprovadas em 2026-07-06)

1. **Satisfação da torcida NÃO reseta** na virada — carrega o humor da temporada anterior.
2. **Cota de TV é paga no momento em que cada competição é criada** (estaduais na geração da temporada; Copa quando gerada; Série A/B na transição para a fase nacional).

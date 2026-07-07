# Spec 004 — Contratos em Tempo de Jogo

> **Status:** Rascunho
> **Origem:** Revisão 2026-07-06 — achado A5 (+ prepara RF-TRF-09)
> **Criada em:** 2026-07-06

## Resumo

Contratos e prazos hoje usam o relógio do mundo real: o contrato mínimo é `now() + 6 meses` e o fim de contrato é `ano civil + N`. Como o jogo avança por rodadas (uma temporada pode ser jogada numa noite) e `joined_at` nasce preenchido para todo jogador, **na prática o mercado inteiro fica bloqueado por 6 meses reais** após criar a liga. Esta spec converte todos os prazos para o calendário do jogo (temporada + rodada global) e implementa o ciclo de expiração/renovação de contratos (RF-TRF-09).

## Escopo

- Unidade de tempo contratual: **temporadas e rodadas do jogo**.
- Contrato mínimo pós-transferência medido em rodadas do jogo.
- `contract_until` como temporada do jogo; expiração ao fim da temporada → jogador vira `free_agent` (com aviso prévio ao técnico).
- UI de renovação de contrato (salário novo + duração) na tela do time.
- Jogadores do seed inicial começam com contratos variados (para não expirar tudo junto).

### Fora de escopo

- IA de times CPU renovando contratos estrategicamente (entra junto com RF-TRF-10, CPU ativa no mercado — spec futura).
- Janelas de transferência (mercado segue sempre aberto).

## User Stories

### US-1 — Mercado funciona no ritmo do jogo

**Como** técnico, **quero** negociar jogadores conforme as rodadas passam, **para** que o mercado não dependa do relógio do mundo real.

**Critérios de aceitação:**
- [ ] **Dado** um jogador recém-transferido, **quando** tento negociá-lo de novo, **então** o bloqueio é por N rodadas do jogo (não meses reais).
- [ ] **Dado** um jogador do elenco inicial (nunca transferido na liga), **então** ele é negociável desde a rodada 1.

### US-2 — Contratos expiram e geram decisões

**Como** técnico, **quero** ser avisado de contratos terminando e poder renovar, **para** não perder jogadores de graça.

**Critérios de aceitação:**
- [ ] **Dado** um contrato que termina na temporada atual, **então** a tela do time destaca o jogador ("último ano de contrato").
- [ ] **Dado** a virada de temporada, **quando** um contrato expira sem renovação, **então** o jogador vira `free_agent` (sem custo de transferência para quem o contratar).
- [ ] **Dado** a UI de renovação, **quando** ofereço salário e duração, **então** o jogador decide pela mesma IA de pontuação do mercado (com expectativa salarial).

### US-3 — Duração de contrato na contratação

**Critérios de aceitação:**
- [ ] **Dado** uma proposta (transferência ou free agent), **então** a duração escolhida (1–5) é em **temporadas do jogo** e `contract_until` reflete `league.season + duração`.

## Casos de borda

- **Liga na última temporada:** contratos expirando são irrelevantes — nenhum aviso necessário.
- **Elenco abaixo do mínimo por expirações:** expiração não pode violar o piso de 15/2 GKs — se violar, o clube renova automaticamente pelo salário atual (regra a calibrar).
- **Jogador expirado no meio de negociação:** ofertas pendentes sobre ele são canceladas na virada.

## Regras de balanceamento (a calibrar)

| Parâmetro | Valor proposto |
|---|---|
| Contrato mínimo pós-transferência | 10 rodadas globais do jogo |
| Duração de contrato | 1–5 temporadas |
| Contratos do seed inicial | sorteio 1–3 temporadas restantes |

## Questões em aberto

1. O contrato mínimo de "6 meses" foi aprovado no design original medido em semanas do jogo — 10 rodadas parece uma boa tradução, ou prefere outro número?
2. Renovação automática forçada quando o elenco ficaria abaixo do mínimo: salário atual mantido, ou com reajuste (ex.: +10%) como "multa" pela gestão ruim?

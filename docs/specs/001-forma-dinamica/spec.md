# Spec 001 — Forma Dinâmica dos Jogadores

> **Status:** Rascunho
> **Requisitos:** RF-CON-03 (+ RNF-05 parcial: substitui o `FormService` morto)
> **Criada em:** 2026-07-06

## Resumo

Jogadores devem ter fases boas e ruins. Hoje o `form_factor` já multiplica a força do jogador na simulação, mas está travado em 1.0 para todos, para sempre — é cosmético. Esta feature faz a forma evoluir com os resultados: quem joga e vence entra em fase, quem perde sai de fase, e quem fica sem jogar regride ao neutro. Isso cria decisões reais de escalação (apostar no jogador em fase vs. o titular de sempre) e alimenta o valor de mercado, que já considera a forma no cálculo.

## Escopo

- Evolução do `form_factor` de cada `CompetitionPlayer` após cada rodada.
- Exibição da forma nas telas de elenco e escalação.
- Remoção do `FormService` antigo (referencia models extintos) e sua substituição por implementação na arquitetura atual.

### Fora de escopo

- Evolução de atributos entre temporadas (RF-CON-04 — spec futura).
- Lesões e seu efeito na forma (RF-CON-05).
- Impacto extra no valor de mercado: o `MarketValueService` já lê `form_factor`; nenhuma mudança lá.

## User Stories

### US-1 — Forma evolui com resultados

**Como** técnico, **quero** que a forma dos meus jogadores suba com vitórias e caia com derrotas, **para** que o desempenho recente do time se reflita nos jogadores.

**Critérios de aceitação:**
- [ ] **Dado** um jogador que participou da partida (titular ou substituto que entrou), **quando** seu time vence, **então** seu `form_factor` aumenta pelo delta de vitória.
- [ ] **Dado** um jogador que participou da partida, **quando** seu time perde, **então** seu `form_factor` diminui pelo delta de derrota.
- [ ] **Dado** um jogador que participou da partida, **quando** seu time empata, **então** seu `form_factor` não recebe delta de resultado.
- [ ] **Dado** qualquer atualização, **então** o `form_factor` permanece dentro dos limites [mín, máx].
- [ ] **Dado** que a rodada foi processada (simulação instantânea ou ao vivo), **então** a forma foi atualizada nos dois modos de partida.

### US-2 — Forma regride ao neutro sem jogar

**Como** técnico, **quero** que jogadores fora do time regridam para a forma neutra, **para** que a fase (boa ou ruim) seja perecível e não um atributo permanente.

**Critérios de aceitação:**
- [ ] **Dado** um jogador do elenco que **não** participou da rodada, **quando** a rodada é processada, **então** seu `form_factor` decai em direção a 1.0 pelo fator de decaimento.
- [ ] **Dado** um free agent, **quando** rodadas passam, **então** sua forma também converge para 1.0.

### US-3 — Forma visível para o técnico

**Como** técnico, **quero** ver a forma de cada jogador nas telas de elenco e de escalação, **para** decidir quem escalar.

**Critérios de aceitação:**
- [ ] **Dado** a tela do time, **então** cada jogador exibe um indicador de forma (rótulo + cor) coerente com seu `form_factor`.
- [ ] **Dado** a tela de escalação, **então** o mesmo indicador aparece para titulares e reservas.
- [ ] **Dado** a tela de scouting do adversário, **então** a forma dos jogadores adversários também é visível (informação pública).

### US-4 — Forma pesa na simulação

**Como** jogo, **quero** que a força efetiva reflita a forma, **para** que fases boas/ruins tenham consequência em campo.

**Critérios de aceitação:**
- [ ] **Dado** o cálculo de força (`strength × fitness × form_factor`, já existente em `SimulatesMatch`), **quando** jogadores têm forma ≠ 1.0, **então** o poder efetivo reflete o multiplicador. (Já funciona; o critério é de regressão.)

## Casos de borda

- **Jogador transferido no meio da temporada** → mantém a forma atual (a fase acompanha o jogador).
- **Substituto que entrou no intervalo** → conta como participante da partida (recebe delta de resultado).
- **Virada de temporada** → forma de todos volta a 1.0 (temporada nova, página nova).
- **Partida de time CPU × CPU** → forma atualiza igual; regra vale para todos os times da liga.

## Regras de balanceamento (a calibrar)

Baseline herdado do design do `FormService` original:

| Parâmetro | Valor proposto |
|---|---|
| Delta vitória | +0.03 |
| Delta empate | 0.00 |
| Delta derrota | −0.03 |
| Decaimento sem jogar (por rodada) | ×0.95 da distância até 1.0 |
| Mínimo | 0.85 |
| Máximo | 1.15 |

## Questões em aberto

1. Jogador que marca gol deve ganhar bônus individual de forma (ex.: +0.01 por gol), ou a forma fica 100% atrelada ao resultado do time?
2. A forma deve aparecer para o adversário no scouting (como proposto em US-3), ou é informação privada de cada técnico?

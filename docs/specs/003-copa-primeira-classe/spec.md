# Spec 003 — Copa do Brasil de Primeira Classe

> **Status:** Rascunho
> **Origem:** Revisão 2026-07-06 — achados A3, M4, M5 e a parte da Copa em A1
> **Criada em:** 2026-07-06

## Resumo

A Copa do Brasil é hoje um subsistema de segunda classe: partidas de técnicos humanos são simuladas como CPU (sem intervalo interativo), não gera bilheteria nem cota de TV (apesar de ter o maior peso de público do jogo), a satisfação da torcida ignora os jogos, os gols não contam para a artilharia, o fitness não varia, e empates no agregado são decididos silenciosamente por seed. Esta spec traz a Copa para o mesmo pipeline das demais competições.

## Escopo

- Partidas ao vivo (1º tempo + intervalo + 2º tempo) para times humanos na Copa.
- Bilheteria e público nas partidas da Copa (peso 1.0 já definido no `FinancialService`).
- Cota de TV da Copa paga aos 64 participantes quando a Copa é gerada.
- Satisfação da torcida reagindo aos resultados da Copa.
- Artilharia e desgaste/recuperação de fitness nas rodadas da Copa.
- `winner_team_id` gravado nas partidas.
- Decisão por pênaltis simulados quando o agregado empata.
- Bracket robusto para número ímpar/não-potência-de-2 de participantes (byes explícitos, nenhum time descartado).

### Fora de escopo

- Prêmio por avanço de fase na Copa (pode virar requisito `💡` no REQUISITOS.md).
- Mudanças no formato (segue ida/volta em todas as fases).

## User Stories

### US-1 — Jogo da Copa é jogado, não assistido

**Como** técnico humano, **quero** disputar meus jogos de Copa ao vivo com substituições no intervalo, **para** ter na Copa a mesma experiência das outras competições.

**Critérios de aceitação:**
- [ ] **Dado** uma partida de Copa com pelo menos um time humano, **quando** a rodada avança, **então** o 1º tempo é simulado e a partida fica em `halftime` aguardando o(s) técnico(s), como nas ligas.
- [ ] **Dado** o intervalo, **então** valem as mesmas regras (até 5 substituições, timeout HvH de 1 minuto).

### US-2 — A Copa movimenta a economia

**Critérios de aceitação:**
- [ ] **Dado** uma partida de Copa, **então** público e renda são calculados com peso 1.0 e creditados ao mandante, com transação no extrato.
- [ ] **Dado** a geração da Copa, **então** cada participante recebe a cota de TV da Copa (5M) com transação no extrato.

### US-3 — A torcida vive a Copa

**Critérios de aceitação:**
- [ ] **Dado** uma rodada de Copa processada, **então** a satisfação dos dois times varia pela tabela oficial (casa/fora × força relativa).

### US-4 — Estatísticas completas

**Critérios de aceitação:**
- [ ] **Dado** gols em partidas de Copa, **então** entram na artilharia.
- [ ] **Dado** uma rodada de Copa, **então** titulares se desgastam e o elenco recupera fitness nas mesmas regras das ligas.
- [ ] **Dado** uma partida decidida, **então** `winner_team_id` é persistido.

### US-5 — Mata-mata sem decisões silenciosas

**Critérios de aceitação:**
- [ ] **Dado** agregado empatado após a volta, **então** a vaga é decidida em pênaltis simulados, registrados nos eventos/narração da partida de volta.
- [ ] **Dado** um número de participantes que não fecha o bracket, **então** os melhores seeds recebem bye explícito na 1ª fase — nenhum time some.

## Casos de borda

- **Time humano demitido entre ida e volta** → partida segue o fluxo do dono atual (CPU ou humano novo).
- **Fase com 2 times (final)** → pênaltis também se aplicam.
- **Artilharia (M5):** o contador atual `goals_scored` é global por jogador na temporada. Ver questão 2.

## Questões em aberto

1. Prêmio em dinheiro por fase avançada na Copa (classificatória → final)? Se sim, valores.
2. Para corrigir a artilharia por competição (M5), preferimos (a) tabela própria `competition_player_stats` por competição — mais correto, permite artilharia separada de estadual/copa/nacional — ou (b) manter contador global e rotular a lista como "gols na temporada"?

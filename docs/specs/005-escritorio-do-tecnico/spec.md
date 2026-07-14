# Spec 005 — Escritório do Técnico

> **Status:** Entregue (2026-07-14)
> **Requisitos:** RF-GES-01, RF-GES-02, RF-GES-03 (absorve RF-SAT-05)
> **Criada em:** 2026-07-14

## Resumo

O técnico humano ganha um **Escritório** dentro de cada liga: uma tela central onde chegam as mensagens do clube — o presidente avisando da cota de TV, o financeiro cobrando pelo saldo, o empresário trazendo propostas por seus jogadores, o auxiliar alertando sobre a escalação. E quando a torcida derruba o técnico, o Escritório vira sua mesa de desempregado: a cada rodada chegam convites de outros clubes, e aceitar um deles o coloca de volta no jogo. Hoje a demissão é um beco sem saída — o `user_id` é anulado e a liga simplesmente some do dashboard do usuário.

## Escopo

- Tela do Escritório por liga (rota dentro do contexto da liga), com caixa de mensagens, estado lida/não lida e badge de não lidas na navegação da liga.
- **O Escritório é a home da liga para o técnico:** com a liga em andamento, acessar a liga leva ao Escritório; a tela atual da liga permanece como área de lobby (antes do início) e administração (dono).
- Tabela nova de mensagens (`league_messages`), com tipo, título, corpo, referência polimórfica opcional ao objeto de origem e rodada de criação.
- Geração de mensagens nos fluxos existentes (categorias aprovadas em 2026-07-14):
  - **Clube/técnico:** demissão efetivada; aviso de satisfação em zona crítica (uma rodada antes do corte).
  - **Financeiro:** cota de TV recebida; resumo semanal de salários; bilheteria como mandante; alerta de saldo baixo/negativo.
  - **Transferências:** proposta recebida por jogador seu; resultado de proposta feita; contra-proposta de retenção pendente.
  - **Escalação/partida:** escalação incompleta antes da rodada; resultado da partida com link para o replay.
- Ciclo de convites pós-demissão (RF-GES-03): a cada `advanceWeek`, usuários demitidos da liga recebem convites de times CPU elegíveis; aceite assume o time (`user_id`).
- Vínculo persistente usuário↔liga que sobrevive à demissão, para a liga continuar visível no dashboard e o Escritório acessível.

### Fora de escopo

- RF-SAT-06 — UI de contratação de técnico CPU do pool (`LeagueCoach`) — spec futura.
- RF-FIN-09 — extrato financeiro completo e navegável (as mensagens financeiras não substituem o extrato).
- Notificações fora do jogo (e-mail real, push).
- Mensagens retroativas: eventos anteriores à entrega não geram mensagens.

## User Stories

### US-1 — Caixa de mensagens do clube

**Como** técnico, **quero** uma tela de escritório com as mensagens do clube, **para** acompanhar tudo que aconteceu com meu time sem varrer as telas do jogo.

**Critérios de aceitação:**
- [ ] **Dado** um técnico com time na liga, **quando** acessa o Escritório, **então** vê suas mensagens em ordem decrescente de criação, com título, tipo, rodada e estado lida/não lida.
- [ ] **Dado** mensagens não lidas, **quando** o técnico navega pela liga, **então** vê um badge com a contagem de não lidas.
- [ ] **Dado** uma mensagem com objeto de origem (proposta, partida), **quando** o técnico a abre, **então** há link para a tela correspondente.
- [ ] **Dado** que o técnico abre uma mensagem, **então** ela é marcada como lida.
- [ ] **Dado** uma liga em andamento, **quando** o técnico acessa a liga, **então** cai no Escritório (home); lobby e administração da liga permanecem acessíveis ao dono.

### US-2 — Mensagens nascem dos eventos do jogo

**Como** técnico, **quero** que os eventos relevantes gerem mensagens automaticamente, **para** não perder propostas, cobranças financeiras ou avisos de escalação.

**Critérios de aceitação:**
- [ ] **Dado** um `advanceWeek` que debita salários, **quando** a rodada termina, **então** o técnico recebe o resumo financeiro da semana.
- [ ] **Dado** uma proposta de transferência por um jogador do técnico, **quando** ela é criada, **então** chega mensagem com link para a tela de ofertas.
- [ ] **Dado** satisfação do time em zona crítica, **quando** a rodada fecha, **então** o técnico recebe o aviso antes da rodada que poderia demiti-lo.
- [ ] **Dado** um time humano com escalação incompleta, **quando** o dono da liga está prestes a avançar a rodada, **então** existe mensagem de alerta gerada na rodada anterior.

### US-3 — Demitido continua no jogo

**Como** técnico demitido, **quero** continuar vendo a liga e receber convites de outros clubes a cada rodada, **para** voltar a jogar sem depender de convite manual.

**Critérios de aceitação:**
- [ ] **Dado** um técnico humano demitido pelo `checkFirings`, **quando** a demissão ocorre, **então** a liga continua listada no dashboard dele e o Escritório acessível, com mensagem explicando a demissão.
- [ ] **Dado** um usuário demitido, **quando** o dono avança a rodada, **então** o usuário recebe convites de times CPU **de divisão igual ou inferior** à do time de onde saiu (divisão nacional quando definida; senão, estadual).
- [ ] **Dado** convites pendentes, **quando** um novo `advanceWeek` roda, **então** os convites antigos expiram e novos são sorteados.
- [ ] **Dado** que o usuário recusa ou ignora todos os convites, **então** segue na liga como observador indefinidamente.
- [ ] **Dado** um usuário demitido (observador), **quando** navega pela liga, **então** vê todas as telas (tabelas, partidas, elencos) mas **nenhuma ação de gestão** (escalação, transferências, preço de ingresso) está disponível.

### US-4 — Aceitar um convite

**Como** técnico demitido, **quero** aceitar um convite, **para** assumir o novo clube imediatamente.

**Critérios de aceitação:**
- [ ] **Dado** um convite válido, **quando** o usuário aceita, **então** `league_teams.user_id` passa a ser dele, os demais convites dele expiram e ele recebe mensagem de boas-vindas do novo clube.
- [ ] **Dado** dois usuários com convite para o mesmo time, **quando** o primeiro aceita, **então** o convite do segundo expira (primeiro a aceitar leva) e a tentativa tardia recebe erro amigável.
- [ ] **Dado** um convite expirado, **quando** o usuário tenta aceitar, **então** recebe erro amigável e a lista é atualizada.

## Casos de borda

- **Nenhum time CPU elegível na divisão igual/inferior** → nessa rodada não há convites; tentar de novo na próxima (elenco de elegíveis muda com outras demissões/aceites).
- **Concorrência no aceite:** validação dentro de `DB::transaction()` com re-checagem de `user_id === null` do time e status do convite.
- **Liga encerrada (`FINISHED`) ou última temporada encerrando** → nenhum convite novo; convites pendentes expiram.
- **Usuário demitido que era dono da liga** → continua dono (avança rodadas) mesmo sem time.
- **Time CPU que convida é o mesmo de onde o usuário foi demitido** → só volta a ser elegível após a carência de ~6 meses de jogo (ver balanceamento). Decidido em 2026-07-14.
- **Volume de mensagens:** um técnico ativo gera ~5 mensagens/rodada; sem poda a tabela cresce sem limite → poda automática (ver balanceamento).

## Regras de balanceamento

| Parâmetro | Valor proposto | Observação |
|---|---|---|
| Convites por rodada por usuário demitido | até 3 | *a calibrar* |
| Validade do convite | 1 rodada (`advanceWeek` seguinte expira) | decidido 2026-07-14 |
| Carência para o ex-clube convidar de volta | 12 rodadas globais (≈ 6 meses do calendário do jogo) | *a calibrar* — medida em rodadas do jogo, não em tempo real (mesmo princípio da spec 004) |
| Zona crítica de satisfação (aviso prévio) | tolerância + 5 pontos | *a calibrar* junto de `SatisfactionService` |
| Alerta de saldo baixo | < 1 semana de folha salarial | *a calibrar* |
| Poda de mensagens | manter as últimas 100 por técnico/liga (lidas e expiradas primeiro) | *a calibrar* |

## Decisões (2026-07-14)

1. **Vínculo pós-demissão:** `LeagueMember` com novo status `fired` (em ligas manuais o registro é criado no momento da demissão).
2. **Acesso do demitido:** vê a liga inteira em modo observador — todas as telas, nenhuma ação de gestão.
3. **Ex-clube:** pode convidar de volta após ~6 meses **de jogo** (12 rodadas globais, a calibrar — nunca tempo real).
4. **Navegação:** o Escritório é a home da liga para o técnico; a tela atual da liga fica para lobby/administração.

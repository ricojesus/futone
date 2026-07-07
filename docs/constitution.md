# Constituição do Futone

> Princípios invioláveis do projeto. Toda spec, plano e implementação deve respeitá-los.
> Mudanças neste documento exigem discussão e aprovação explícita do dono do projeto.
> Versão 1.0 — 2026-07-06

---

## Artigo I — Processo (Spec-Driven Development)

1. Nenhuma feature é implementada sem passar pelo ciclo **spec → plan → tasks → implement**, em `docs/specs/NNN-nome/`.
2. Cada etapa tem um **portão de aprovação**: a spec só vira plano depois de aprovada; o plano só vira tarefas depois de aprovado.
3. A spec é o critério de pronto. Código que diverge da spec está errado, mesmo que "funcione". Se a divergência for desejável, atualiza-se a spec primeiro.
4. `docs/REQUISITOS.md` é o inventário e backlog: toda spec nasce de um ou mais requisitos de lá (`💡` ou `❌`) e, ao ser entregue, atualiza o status deles com link para a spec.
5. Correções de bug triviais e ajustes cosméticos não exigem spec; mudança de regra de jogo, economia ou estrutura de dados sempre exige.

## Artigo II — Arquitetura

1. A hierarquia é `League → LeagueTeam → CompetitionTeam → CompetitionPlayer` e `League → Competition → CompetitionMatch → MatchState`. Features novas se encaixam nela; não se criam hierarquias paralelas.
2. `competition_players` **não tem** `competition_id` — o vínculo do jogador é sempre pelo `league_team_id`.
3. A lineup persiste **apenas os 11 titulares**; reservas são sempre derivados do elenco ativo.
4. Regra de negócio vive em **Services** (`app/Services/`); controllers apenas validam, autorizam e delegam.
5. A temporada segue as fases `state → copa → national`, com transições automáticas via `GlobalRoundService`.

## Artigo III — Banco de Dados

1. Toda mudança de schema é feita por migration nomeada `YYYY_MM_DD_HHMMSS_descricao.php`.
2. `users.id` é `bigint` (`foreignId`); todos os demais PKs são UUID (`foreignUuid`).
3. FKs de `league_id`, `competition_id` e afins usam `cascadeOnDelete()`.
4. Escritas que tocam múltiplas tabelas rodam dentro de `DB::transaction()`.

## Artigo IV — Autorização

1. Toda action valida a cadeia de posse no início: `$competition->league_id === $league->id`, `$match->competition_id === $competition->id`, e que o `LeagueTeam` pertence ao usuário quando a ação é de técnico.
2. Guards simples usam `abort_unless()`.

## Artigo V — Interface

1. Tema dark: `bg-slate-900` / `bg-slate-800` / `border-slate-700`; destaque do time do usuário em emerald (`bg-emerald-500/10`, `text-emerald-400`).
2. Interatividade exclusivamente com Alpine.js — nunca Vue ou React.
3. Feedback ao usuário via flash messages (`session('success'|'error'|'info')`).
4. Textos da interface em português brasileiro.

## Artigo VI — Qualidade

1. **Toda regra de negócio nova entra com testes Pest** cobrindo os critérios de aceitação da spec. (Dívida pré-existente está mapeada em RNF-01.)
2. Valores de balanceamento (tabelas de satisfação, pesos de IA, fatores econômicos) ficam em constantes nomeadas no topo do service, nunca soltos no meio do código — são o alvo de calibração.
3. Código morto não permanece no repositório: service que perdeu o chamador é removido ou religado na mesma entrega.
4. Documentação e specs em português brasileiro; identificadores de código em inglês, seguindo o padrão existente.

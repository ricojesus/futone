# Plan NNN — <Nome da Feature>

> **Status:** Rascunho | Aprovado
> **Spec:** ./spec.md (só escrever o plano após a spec estar Aprovada)

## Decisões técnicas

Abordagem escolhida e alternativas descartadas (com o porquê, em uma linha cada).

## Modelo de dados

Migrations necessárias: tabelas/colunas novas ou alteradas, tipos, defaults, índices.

## Componentes afetados

| Camada | Arquivo | Mudança |
|---|---|---|
| Service | `app/Services/...` | criar/alterar ... |
| Controller | ... | ... |
| Rota | ... | ... |
| View | ... | ... |

## Pontos de integração

Onde a feature se conecta ao fluxo existente (ex.: `GlobalRoundService::advance`, `SeasonTransitionService::advanceSeason`).

## Estratégia de testes

Quais testes Pest cobrem cada critério de aceitação da spec.

## Riscos e mitigação

O que pode quebrar (dados existentes de ligas em andamento, performance, etc.) e como tratar.

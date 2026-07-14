# Plan 005 — Escritório do Técnico

> **Status:** Aprovado (2026-07-14)
> **Spec:** ./spec.md (Aprovada em 2026-07-14)

## Decisões técnicas

- **Rodada global na liga (`leagues.global_round`):** contador monotônico incrementado a cada `GlobalRoundService::advance()`. É a unidade de tempo das mensagens, da expiração de convites e da carência do ex-clube. *Alternativa descartada:* derivar de `competitions.current_round` — ambíguo entre fases e competições paralelas. **Bônus:** vira a fundação da spec 004 (contratos em tempo de jogo).
- **`MessageService` central com helpers tipados** (`sendFinancial()`, `sendTransfer()`, ...) chamado pelos services existentes nos pontos de integração. *Alternativa descartada:* eventos/listeners do Laravel — indireção desnecessária para um único consumidor; os services já se chamam diretamente (padrão do projeto).
- **Convites em tabela própria (`league_invitations`)**, não como tipo de mensagem: convite tem ciclo de vida (pending/accepted/expired/declined) e ação transacional; mensagem é só leitura. O Escritório exibe os dois.
- **Vínculo do demitido:** `LeagueMember.status = 'fired'` (decisão da spec), com colunas do contexto da demissão para o filtro de divisão e a carência do ex-clube.
- **Observador (read-only):** nenhuma permissão nova — as actions de gestão já exigem `user_id` do time; a tarefa é auditar as *views* para não quebrarem com `userTeam = null` e esconderem botões de ação.

## Modelo de dados

**M1 — `leagues.global_round`** — `unsignedInteger`, default 0. Backfill: ligas em andamento recebem o maior `current_round` entre suas competições da fase atual (aproximação aceitável; só afeta carências/expirations futuras).

**M2 — `league_messages`** (nova):

| Coluna | Tipo | Nota |
|---|---|---|
| `id` | uuid PK | |
| `league_id` | `foreignUuid` → leagues, `cascadeOnDelete` | |
| `user_id` | `foreignId` → users, `cascadeOnDelete` | destinatário (`users.id` é bigint) |
| `league_team_id` | `foreignUuid` nullable → league_teams, `nullOnDelete` | time no contexto da mensagem |
| `type` | string(40) | `financial`, `transfer`, `match`, `lineup`, `club`, `invitation` |
| `title` / `body` | string / text | |
| `subject_type` / `subject_id` | nullableMorphs (uuid) | link para proposta, partida, transação |
| `global_round` | unsignedInteger | rodada em que foi gerada |
| `read_at` | timestamp nullable | |
| índice | (`league_id`, `user_id`, `read_at`) | consulta do badge |

**M3 — `league_invitations`** (nova): `id` uuid, `league_id` FU cascade, `user_id` FI cascade, `league_team_id` FU cascade (time que convida), `status` enum(`pending`,`accepted`,`declined`,`expired`) default pending, `global_round` (rodada de criação; expira quando `< leagues.global_round`), timestamps. Índice (`league_id`, `user_id`, `status`).

**M4 — `league_members`**: enum `status` ganha `fired` (padrão das migrations de gap de enum de 2026-07); colunas novas `fired_from_league_team_id` (FU nullable → league_teams, `nullOnDelete`) e `fired_at_global_round` (unsignedInteger nullable).

## Componentes afetados

| Camada | Arquivo | Mudança |
|---|---|---|
| Model | `LeagueMessage`, `LeagueInvitation` | novos, com constantes de type/status |
| Model | `LeagueMember` | `STATUS_FIRED`, fillable/casts novos |
| Model | `League` | `global_round` no fillable |
| Service | `app/Services/MessageService.php` | **novo** — `send()` + helpers tipados + poda (últimas 100 por user/liga) |
| Service | `app/Services/InvitationService.php` | **novo** — `expireAndGenerate(League)`, `accept()`, `decline()`; filtro de divisão igual/inferior + carência do ex-clube |
| Service | `GlobalRoundService::advance` | incrementa `global_round`; chama `InvitationService::expireAndGenerate`; mensagens de resultado e alerta de escalação incompleta |
| Service | `FinancialService` | mensagens em `payTvQuotaFor`, `deductWeeklySalaries` (resumo + alerta de saldo), `processMatchRevenue` |
| Service | `TransferService` | mensagens em `makeDirectOffer` (vendedor humano), `resolvePlayerDecision`/`resolveTeamDecision` (comprador), `retentionOffer` |
| Service | `SatisfactionService` | remove o TODO da linha 239: cria/atualiza `LeagueMember` fired + mensagem de demissão; aviso de zona crítica (tolerância + 5) |
| Controller | `app/Http/Controllers/OfficeController.php` | **novo** — `index`, `readMessage` (marca lida + redireciona ao subject), `acceptInvitation`, `declineInvitation` |
| Controller | `LeagueController::show` | liga `in_progress` + usuário técnico/demitido (não-dono) → redirect para o Escritório; dono mantém a tela atual com link para o próprio Escritório |
| Rota | `routes/web.php` | `GET leagues/{league}/office`, `POST .../office/messages/{message}/read`, `POST .../office/invitations/{invitation}/accept\|decline` |
| View | `leagues/office/index.blade.php` | **nova** — inbox (Alpine para abrir/filtrar), bloco de convites quando demitido |
| View | layout de navegação da liga | badge de não lidas; link "Escritório" |
| View | `teams/show`, `lineup/edit`, `transfers/*` | auditar para `userTeam = null` (observador): esconder ações |

## Pontos de integração

- `GlobalRoundService::advance()` — coração do ciclo: `global_round++` → simula rodada (mensagens de partida/financeiro saem dos services chamados) → `InvitationService::expireAndGenerate()` → aviso de escalação para a rodada seguinte.
- `SatisfactionService::checkFirings()` — demissão de humano: `LeagueMember` fired (cria se liga manual), mensagem `club`, e o fluxo existente de `user_id = null` permanece.
- `OfficeController::acceptInvitation()` — `DB::transaction` com lock: re-checa `status = pending`, `global_round` vigente e `league_teams.user_id === null`; efetiva `user_id`, expira convites concorrentes (do usuário e do time), `LeagueMember` volta a `assigned`, mensagem de boas-vindas.
- Autorização (RNF-02): toda action do Office valida `message/invitation → league` e `user_id === auth()->id()`.

## Estratégia de testes

Pest em MySQL `futone_testing`, helpers de `tests/Pest.php`:

| Teste | Cobre |
|---|---|
| `OfficeMessagesTest` | US-1: listagem, marcar lida, badge count, escopo por usuário/liga (não vaza mensagem de outro técnico) |
| `MessageGenerationTest` | US-2: salários, cota de TV, proposta recebida, resultado de partida, aviso de satisfação crítica |
| `FiringAndInvitationTest` | US-3: demissão cria member fired + liga visível; geração por rodada com filtro de divisão; expiração; carência do ex-clube (12 rodadas) |
| `InvitationAcceptTest` | US-4: aceite efetiva `user_id`; corrida (segundo aceite falha amigável); convite expirado rejeitado; observador sem ações de gestão |

## Riscos e mitigação

- **Ligas em andamento** sem `LeagueMember` (atribuição manual) e com `global_round = 0` → backfill na M1 e criação lazy do member na demissão; nenhum dado existente é alterado além disso.
- **Volume de mensagens** (27+ estaduais × rodadas) → mensagens só para times com `user_id` humano; poda a 100 por user/liga no próprio `MessageService`.
- **Redirect da home** pode surpreender o dono da liga → dono não é redirecionado; técnicos têm link de volta para a página clássica da liga.
- **Deadlock/corrida no aceite** → transação curta com `lockForUpdate` no `LeagueTeam` e no convite.

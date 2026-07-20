# Futone — Guia para o Agente

Jogo de gestão de futebol multiplayer (estilo football manager) em Laravel 11. Este arquivo descreve a arquitetura, convenções e armadilhas do projeto para que o agente trabalhe sem rederivação de contexto.

---

## Stack

- **Backend:** Laravel 11, PHP 8.2+
- **Frontend:** Blade + Alpine.js + Tailwind CSS (dark theme, slate palette)
- **Banco:** MySQL (produção)
- **Testes:** Pest
- **Build:** Vite

---

## Arquitetura Central

### Hierarquia de objetos

```
League                          ← mundo / sala de jogo
  └── LeagueTeam                ← clube do jogador dentro desta liga (1 por user)
        └── CompetitionTeam     ← participação do clube em uma competição específica
              └── CompetitionPlayer ← jogador snapshot dentro desta competição
  └── Competition               ← campeonato (estadual, copa, nacional)
        └── CompetitionMatch    ← partida
              └── MatchState    ← estado salvo do intervalo (dados do 1º tempo)
  └── LeagueMember              ← fila do lobby (sorteio automático de times)
```

### Regra crítica — CompetitionPlayer

`competition_players` **não tem `competition_id`**. O vínculo é pelo `league_team_id`:

```php
// CORRETO
CompetitionPlayer::where('league_team_id', $leagueTeam->id)

// ERRADO — coluna não existe
CompetitionPlayer::where('competition_id', $competition->id)
```

Jogadores são vinculados ao `LeagueTeam` (clube na liga), não à competição.

### Regra crítica — Escalação

`competition_lineup_players` armazena **apenas os 11 titulares**. Os reservas nunca são persistidos — são buscados dinamicamente do elenco:

```php
// Titulares: vêm da lineup
$starters = $lineup->players()->where('is_starter', true)->get();

// Reservas: elenco ativo menos os titulares
$bench = $leagueTeam->players()
    ->where('status', 'active')
    ->whereNotIn('id', $starterIds)
    ->get();
```

---

## Fases da Temporada

```
League::current_phase:  'state' → 'copa' → 'national'
```

| Fase | Competitions geradas | Formato |
|---|---|---|
| `state` | A1 + A2 por estado (27 estados) | Liga (turno + returno) |
| `copa` | Copa do Brasil | Knockout (ida + volta, bracket_slot) |
| `national` | Série A + Série B | Liga (turno + returno) |

A transição é automática via `GlobalRoundService::transitionPhase()` quando todas as partidas da fase estão `finished`.

**Promoção/Rebaixamento** entre A1/A2 e Série A/Série B é calculada em `SeasonTransitionService::calculateTransitions()`.

---

## Services Principais

| Service | Responsabilidade |
|---|---|
| `GlobalRoundService` | Avança uma rodada em todas as competições da fase atual; cuida das transições de fase |
| `LeagueGeneratorService` | Cria as competitions e competition_teams ao iniciar uma liga |
| `CompetitionRoundService` | Avança uma rodada de uma competition específica |
| `CopaBrasilService` | Gera o bracket da Copa e avança suas fases |
| `LiveMatchSimulator` | Simula 1º tempo (salva em MatchState) e 2º tempo (finaliza a partida) |
| `MatchSimulator` | Simulação completa instantânea (CPU vs CPU) |
| `MatchEngine` | Motor de eventos: processa os 90 minutos tick a tick |
| `MatchNarrator` | Gera narração textual para cada evento |
| `SeasonTransitionService` | Calcula promoções/rebaixamentos e gera competitions da temporada seguinte |
| `CalendarGeneratorService` | Gera o calendário de partidas de uma competition |
| `CpuLineupService` | Gera e persiste a escalação automática de um time CPU a cada rodada, escolhendo os 11 melhores por posição considerando fitness atual (desgaste) |

---

## Fluxo de Partida ao Vivo

```
advanceRound() → LiveMatchSimulator::simulateFirstHalf()
    → salva em MatchState (state JSON com events, scores, stats)
    → CompetitionMatch.status = 'halftime'

Jogador acessa /matches/{match}/halftime
    → faz substituições (até 5)
    → POST resume → LiveMatchSimulator::simulateSecondHalf()
    → atualiza standings, artilharia, fitness
    → CompetitionMatch.status = 'finished'
```

---

## Lobby (Sorteio Automático)

Quando `league.team_assignment = 'auto'`:

1. **O dono já entra na fila ao criar a liga** (`LeagueController::store` cria seu `LeagueMember` com `status = waiting`)
2. Demais jogadores entram via `POST /leagues/{league}/lobby/join`
3. Dono pode clicar "Sortear Times" (`POST /leagues/{league}/lobby/draw`) — ou simplesmente iniciar/gerar a liga: `start` e `generate` sorteiam automaticamente quem estiver na fila (`LobbyService::drawWaitingMembers`)
4. O sorteio faz shuffle de membros e `LeagueTeam`s CPU (`team_id` preenchido), atribui `user_id` e libera o técnico CPU para o pool

Quando `team_assignment = 'manual'`: fluxo antigo via `LeagueTeamController`.

---

## Duração de Temporadas

- `league.max_seasons` (nullable) — null = sem limite
- `league.season_start` — ano da primeira temporada (imutável)
- `League::isLastSeason()` — retorna true quando `(season - season_start + 1) >= max_seasons`
- `SeasonTransitionService::advanceSeason()` auto-encerra a liga se `isLastSeason()` for true após o bump de season

---

## Modelos e Constantes

### League

```php
League::STATUS_WAITING | IN_PROGRESS | FINISHED | CANCELLED
League::PHASE_STATE | PHASE_COPA | PHASE_NATIONAL
League::ASSIGNMENT_MANUAL | ASSIGNMENT_AUTO
```

### Competition

```php
Competition::COMPETITION_TYPE_STATE | NATIONAL | COPA
Competition::FORMAT_LEAGUE | FORMAT_KNOCKOUT
Competition::DIVISION_FIRST | DIVISION_SECOND
Competition::STATUS_WAITING | IN_PROGRESS | FINISHED | CANCELLED
```

---

## Convenções de Código

### Migrations

- Nomenclatura: `YYYY_MM_DD_HHMMSS_descricao.php`
- Sempre usar `cascadeOnDelete()` em FKs de `league_id`, `competition_id`, etc.
- `users.id` é `bigint unsigned` — usar `foreignId('user_id')`, **não** `foreignUuid()`
- Todos os outros PKs são UUID — usar `foreignUuid()`

### Controllers

- Sempre validar `$competition->league_id === $league->id` e `$match->competition_id === $competition->id` no início das actions
- Usar `abort_unless()` para guards simples
- Transações com `DB::transaction()` para qualquer escrita que toque múltiplas tabelas

### Views

- Tema dark: `bg-slate-900`, `bg-slate-800`, `border-slate-700`
- Destaque do time do usuário: `bg-emerald-500/10`, `text-emerald-400`
- Cards: `rounded-2xl border border-slate-700 bg-slate-900`
- Flash messages: `session('success')`, `session('error')`, `session('info')`
- Interatividade: Alpine.js (`x-data`, `x-show`, `x-transition`) — nunca Vue ou React

---

## Comandos Artisan Customizados

```bash
# Corrige slugs duplicados de times após importação CSV
php artisan teams:fix-slugs

# Reseta uma liga completamente (apaga competitions e regenera)
php artisan league:reset {league_id}
```

---

## Armadilhas Conhecidas

| Situação | Problema | Solução |
|---|---|---|
| Buscar jogadores de uma competição | Não existe `competition_id` em `competition_players` | Usar `league_team_id` via `CompetitionTeam` |
| Bench no intervalo | Lineup só salva titulares | Buscar do elenco ativo excluindo os 11 titulares |
| Substituições no intervalo | Reserva pode não estar em `lineup_players` | Criar novo registro se não existir (não apenas atualizar) |
| Transição de fase | `state → copa`, não direto para `national` | `GlobalRoundService::transitionToCopa()` chama `CopaBrasilService::generate()` |
| `users.id` | É `bigint`, não UUID | Sempre usar `foreignId('user_id')` em migrations |
| `teams:fix-slugs` | Update antes de delete viola unique | Apagar o duplicado primeiro, depois atualizar o original |
| Iniciar liga de sorteio | Depois de `in_progress` o `draw` do lobby não roda mais (fila ficaria órfã) | `start`/`generate` chamam `LobbyService::drawWaitingMembers` antes de iniciar |
| Intervalo sem lineup salva | Painel de substituições exige `$lineup` — sem ele o botão de 2º tempo sumia e a liga travava (`hasPendingLive`) | `halftime.blade.php` tem painel fallback só com o botão; `resumeSecondHalf` não exige lineup |
| Dono avança sem escalar | Time jogaria no 4-4-2 automático sem aviso | `advanceWeek` bloqueia e redireciona o dono para `lineup.edit`; outros humanos jogam no automático (decisão 2026-07-14) |
| Times CPU nunca desgastavam | `applyFitnessDegradation()` só desconta fitness de jogadores com `CompetitionLineup` persistida da rodada; times CPU nunca tinham uma (usavam fallback `autoSelectPlayers()` recalculado e descartado a cada partida) | Corrigido (2026-07-20): `SimulatesMatch::loadLineup()` chama `CpuLineupService::generateForRound()` quando o time é CPU e não há lineup da rodada, persistindo os 11 melhores por fitness/power — isso alimenta a degradação normalmente |

---

## Estrutura de Pastas Relevante

```
app/
  Http/Controllers/
    CompetitionController.php   ← show, join, advanceRound
    LeagueController.php        ← CRUD de liga, generate, advanceWeek, seasonSummary
    LeagueLobbyController.php   ← join (fila) e draw (sorteio)
    LeagueTeamController.php    ← escolha manual de time
    MatchController.php         ← show, halftime, resumeSecondHalf
    LineupController.php        ← edit/update escalação
  Models/
    League.php / LeagueTeam.php / LeagueMember.php
    Competition.php / CompetitionTeam.php / CompetitionPlayer.php
    CompetitionMatch.php / MatchState.php
    CompetitionLineup.php / CompetitionLineupPlayer.php
  Services/                     ← ver tabela acima

resources/views/leagues/
  show.blade.php                ← página da liga (lobby, competições)
  create.blade.php              ← formulário de criação
  competitions/
    show.blade.php              ← tabela + agenda (liga) ou bracket (copa)
    matches/
      show.blade.php            ← replay completo da partida
      halftime.blade.php        ← intervalo interativo

database/migrations/            ← migrations em ordem cronológica
storage/app/csv-templates/
  times.csv                     ← 221 times do Brasil (fonte dos seeders)
```

---

## Base de Dados de Futebol

- **27 estados** brasileiros (`BrazilianStatesSeeder`)
- **221 times** reais com `state_division` (A1/A2) e `national_division` (serie_a/serie_b/none)
- Seeder: `BrazilianTeamsSeeder` lê `storage/app/csv-templates/times.csv`

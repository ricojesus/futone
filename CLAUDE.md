# Futone

Jogo no estilo futmanager com ligas online e partidas multiplayer em tempo real (PvP e PvE com IA).

**Stack:** Laravel 11 + Blade + Alpine.js + Tailwind CSS + SQLite + Pest + Vite

**Perfis de usuário:** Jogador (cria/entra em ligas, joga partidas) e Administrador (gerencia times, jogadores, países).

## Estrutura atual

- `app/Services/MatchEngine.php` — motor de simulação de partidas (posse, setores, estatísticas)
- `app/Services/TeamsRepository.php` — times e elencos hardcoded em memória (provisório)
- `app/Http/Controllers/MatchController.php` — endpoint de partida (sem auth ainda)
- Auth scaffolding completo; campo `tipo` em `users` distingue Jogador de Administrador

## Domínios a modelar

| Domínio | Entidades centrais |
|---|---|
| Clube / Elenco | `Team`, `Player`, `PlayerStat` |
| Liga | `League`, `LeagueMember`, `Season` |
| Calendário | `Match`, `MatchEvent`, `MatchResult` |
| Usuário | `User` (tipo: jogador/admin) |

## Dívida técnica conhecida

- `TeamsRepository` usa arrays estáticos — times precisam ser persistidos no banco
- `MatchController::play()` ignora autenticação e não aceita parâmetros de times
- `MatchEngine` opera sobre arrays PHP puros, sem vínculo com Eloquent
- Nenhum Model além de `User` existe ainda
- Rotas de jogo ainda não estão em `routes/web.php`

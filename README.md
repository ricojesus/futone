<p align="center">
  <img src="public/images/logos/futone.png" alt="Logo Futone" width="220" />
</p>

<h1 align="center">Futone</h1>

<p align="center">
  Jogo de gestão de futebol multiplayer com ligas online, simulação tática em tempo real e temporadas completas do futebol brasileiro.
</p>

---

## O que é o Futone

Futone é um jogo no estilo football manager focado em estratégia, gestão de elenco e competições online entre amigos. O jogador assume o controle de um clube brasileiro e disputa campeonatos estaduais, Copa do Brasil e Brasileirão — tudo dentro de uma liga criada por ele ou por um amigo.

O diferencial central é a **simulação ao vivo com intervalo interativo**: o primeiro tempo é simulado automaticamente e exibido em replay narrado minuto a minuto; no intervalo, o técnico analisa as estatísticas e faz substituições antes de iniciar o segundo tempo. Cada decisão importa.

---

## Fluxo de Jogo

```
Criar Liga → Convidar Amigos → Escolher / Sortear Times
     ↓
Temporada começa: Fase Estadual (A1 + A2 por estado)
     ↓
Copa do Brasil (mata-mata com ida e volta)
     ↓
Brasileirão (Série A + Série B, com acesso e rebaixamento)
     ↓
Resumo da Temporada → Promoções / Rebaixamentos → Nova Temporada
```

Cada fase é desbloqueada automaticamente quando a anterior termina. A liga pode durar de 1 temporada até indefinidamente, conforme configurado pelo criador.

---

## Diagramas

### Estrutura de Dados

```mermaid
erDiagram
    User {
        bigint id
        string name
        string email
    }
    League {
        uuid id
        string name
        enum status
        enum current_phase
        enum team_assignment
        int season
        int season_start
        int max_seasons
    }
    LeagueMember {
        uuid id
        enum status
    }
    LeagueTeam {
        uuid id
        string name
    }
    Competition {
        uuid id
        string name
        enum competition_type
        enum format
        enum division
        enum status
        int current_round
        int total_rounds
    }
    CompetitionTeam {
        uuid id
        string name
        int points
        int wins
        int draws
        int losses
        int goals_for
        int goals_against
    }
    CompetitionPlayer {
        uuid id
        string name
        enum position
        int strength
        int stamina
        int fitness
        int age
        int goals_scored
        decimal form_factor
    }
    CompetitionMatch {
        uuid id
        int round
        int leg
        enum status
        int home_score
        int away_score
        json data
    }
    MatchState {
        uuid id
        json state
    }
    CompetitionLineup {
        uuid id
        string formation
        int round
    }
    CompetitionLineupPlayer {
        uuid id
        enum role
        bool is_starter
        int slot
    }
    State {
        uuid id
        string name
        string code
    }
    Team {
        uuid id
        string name
        string slug
        enum state_division
        enum national_division
    }

    User ||--o{ League : "cria (owner)"
    User ||--o{ LeagueMember : "entra no lobby"
    User ||--o| LeagueTeam : "gerencia"
    League ||--o{ LeagueMember : ""
    League ||--o{ LeagueTeam : ""
    League ||--o{ Competition : ""
    LeagueTeam ||--o{ CompetitionTeam : ""
    LeagueTeam ||--o{ CompetitionPlayer : "elenco"
    LeagueTeam ||--o{ CompetitionLineup : ""
    Competition ||--o{ CompetitionTeam : ""
    Competition ||--o{ CompetitionMatch : ""
    Competition }o--|| State : "estaduais"
    CompetitionTeam ||--o{ CompetitionPlayer : ""
    CompetitionMatch ||--|| CompetitionTeam : "home"
    CompetitionMatch ||--|| CompetitionTeam : "away"
    CompetitionMatch ||--o| MatchState : "intervalo"
    CompetitionLineup ||--o{ CompetitionLineupPlayer : ""
    CompetitionLineupPlayer }o--|| CompetitionPlayer : ""
    Team }o--|| State : ""
```

---

### Fluxo da Temporada

```mermaid
flowchart TD
    A([Criar Liga]) --> B{Modo de entrada}
    B -- Escolha livre --> C[Jogadores escolhem\nseus times]
    B -- Sorteio automático --> D[Jogadores entram\nno lobby]
    D --> E[Dono sorteia\nos times]
    C --> F
    E --> F[Gerar Competições]

    F --> G

    subgraph ESTADUAL["🏟 Fase Estadual"]
        G[A1 + A2 por estado\n27 estados × 2 divisões]
        G --> H{Todas as\nrodadas concluídas?}
        H -- Não --> I[Avançar rodada]
        I --> H
        H -- Sim --> J[Promoção / Rebaixamento\nA1 ↔ A2]
    end

    J --> K

    subgraph COPA["🏆 Copa do Brasil"]
        K[Bracket nacional\nida + volta]
        K --> L{Fase\nencerrada?}
        L -- Não --> M[Avançar rodada\ndo bracket]
        M --> L
    end

    L -- Sim --> N

    subgraph NACIONAL["🇧🇷 Brasileirão"]
        N[Série A + Série B\nnacional]
        N --> O{Todas as\nrodadas concluídas?}
        O -- Não --> P[Avançar rodada]
        P --> O
        O -- Sim --> Q[Promoção / Rebaixamento\nSérie A ↔ Série B]
    end

    Q --> R[Resumo da Temporada]
    R --> S{Limite de\ntemporadas atingido?}
    S -- Sim --> T([Liga Encerrada])
    S -- Não --> U[Nova Temporada\nseason + 1]
    U --> G
```

---

### Fluxo de uma Partida ao Vivo

```mermaid
sequenceDiagram
    actor Dono
    actor Jogador
    participant Sistema

    Dono->>Sistema: Avançar rodada
    Sistema->>Sistema: CPU × CPU → simula completo (MatchSimulator)
    Sistema->>Sistema: Humano × CPU → simula 1º tempo (LiveMatchSimulator)
    Sistema->>Sistema: Salva em MatchState (events, scores, stats)
    Sistema-->>Jogador: Redireciona para /halftime

    Jogador->>Sistema: Acessa página do intervalo
    Sistema-->>Jogador: Replay narrado do 1º tempo (Alpine.js)
    Sistema-->>Jogador: Estatísticas + painel de substituições (OVR + fitness)

    Jogador->>Sistema: Seleciona até 5 substituições
    Jogador->>Sistema: POST → Iniciar 2º tempo

    Sistema->>Sistema: Aplica substituições na lineup
    Sistema->>Sistema: Simula 2º tempo (LiveMatchSimulator)
    Sistema->>Sistema: Atualiza standings, artilharia e fitness
    Sistema->>Sistema: status = finished

    Sistema-->>Jogador: Redireciona para replay completo
```

---

## Funcionalidades

### Ligas

- **Criação de ligas** públicas ou privadas (via código de convite).
- **Dois modos de entrada:**
  - **Escolha livre** — cada jogador entra e escolhe seu time.
  - **Sorteio automático** — jogadores entram no lobby e o dono sorteia os times de uma vez (evita vantagem de quem entra primeiro).
- **Duração configurável** — 1, 2, 3, 5 temporadas, número personalizado ou sem limite. Ao atingir o limite, a liga é encerrada automaticamente.
- Lobby com lista de participantes e status de sorteio em tempo real.

### Temporada e Fases

| Fase | Formato | Divisões |
|---|---|---|
| **Estadual** | Liga (turno e returno) | A1 e A2 por estado (27 estados) |
| **Copa do Brasil** | Mata-mata (ida + volta) | Único bracket nacional |
| **Brasileirão** | Liga (turno e returno) | Série A e Série B |

- Promoção e rebaixamento automáticos entre A1/A2 e Série A/Série B.
- Acumulação de pontos, saldo de gols e confronto direto.
- Classificação ao vivo com zonas coloridas (campeão, acesso, rebaixamento).
- Artilharia individual com ranking por competição.

### Partidas e Simulação

- **Motor de simulação** (`MatchEngine`) processa os 90 minutos evento a evento, levando em conta:
  - Força (OVR) dos jogadores titulares.
  - Nível de fitness individual.
  - Fator de forma (`form_factor`) do jogador na competição.
  - Stamina para degradação de performance ao longo do jogo.
- **Replay narrado** — o primeiro tempo é reproduzido minuto a minuto com narração textual, gols destacados e barra de progresso.
- **Intervalo interativo:**
  - Estatísticas do 1º tempo (posse, chutes, chutes no gol, gols).
  - Painel de substituições com OVR e barra de saúde de cada jogador.
  - Até 5 substituições antes do 2º tempo.
- **Segundo tempo simulado** após confirmação do técnico.
- Partidas **CPU vs CPU** simuladas instantaneamente; partidas com humanos passam pelo intervalo interativo.

### Elenco e Jogadores

- Cada clube possui um elenco com atributos individuais por competição (`competition_players`):
  - **OVR (strength)** — poder geral do jogador.
  - **Stamina** — influência na degradação de fitness.
  - **Fitness** — saúde física; decresce após cada partida e varia por idade.
  - **Potential** — teto de crescimento.
  - **Form factor** — multiplicador de desempenho na competição atual.
- Jogadores envelhecem +1 ano a cada virada de temporada.
- Fitness resetado para 100 no início de cada temporada.
- Gols marcados acumulados por competição (artilharia).

### Escalação

- Formações disponíveis (4-3-3, 4-4-2, 3-5-2, etc.) com validação de quantidade por posição.
- Escalação padrão (round 0) aplicada a todas as rodadas.
- Override por rodada específica (criado automaticamente ao fazer substituições no intervalo).
- Interface drag-and-drop com visualização do campo.

### Degradação de Fitness

Após cada partida completa, os titulares sofrem desgaste calculado por:

```
perda = rand(10, 18) × (1.3 - stamina / 90) × fator_de_idade
```

Fator de idade: jogadores acima de 31 anos se recuperam mais devagar. O fitness mínimo após uma partida é 35.

---

## Base de Dados do Futebol Brasileiro

- **27 estados** com campeonatos A1 e A2 próprios.
- **221 times** reais do Brasil, classificados por divisão estadual e nacional.
- Times organizados por `state_division` (A1/A2) e `national_division` (Série A / Série B / nenhuma).
- Copa do Brasil gerada com bracket de eliminatória e confrontos por `bracket_slot`.

---

## Perfis de Usuário

### Jogador

- Criar e gerenciar ligas.
- Entrar em ligas via convite ou busca pública.
- Assumir controle de um clube (em todas as competições da liga).
- Configurar escalação e formação.
- Jogar partidas ao vivo, fazer substituições no intervalo.
- Acompanhar classificação, artilharia e histórico de partidas.

### Administrador

- Gerenciar jogadores do sistema (cadastro, upload CSV).
- Gerenciar times, países, estados e treinadores.
- Gerenciar campeonatos e configurações globais.

---

## Stack Tecnológico

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 11.x |
| Frontend | Blade + Alpine.js |
| Estilos | Tailwind CSS |
| Banco de dados | MySQL (produção) / SQLite (desenvolvimento) |
| Build | Vite |
| Testes | Pest Framework |

---

## Como Rodar o Projeto

### Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL ou SQLite

### Instalação

```bash
# 1. Clone o repositório
git clone <seu-repositorio>
cd futone

# 2. Dependências PHP
composer install

# 3. Ambiente
cp .env.example .env
php artisan key:generate

# 4. Dependências frontend
npm install

# 5. Banco de dados e dados iniciais
php artisan migrate
php artisan db:seed --class=BrazilianStatesSeeder
php artisan db:seed --class=BrazilianTeamsSeeder

# 6. Inicie
npm run dev
php artisan serve
```

Acesse em: **http://localhost:8000**

### Comandos Úteis

```bash
# Corrigir slugs duplicados de times (após importação CSV)
php artisan teams:fix-slugs

# Resetar uma liga para novo teste (apaga dados e regera competições)
php artisan league:reset {league_id}
```

---

## Testes

```bash
./vendor/bin/pest
```

---

## Build de Produção

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Licença

Este projeto está sob a licença MIT.

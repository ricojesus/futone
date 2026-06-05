{{--
    Exibe o escudo de um time com fallback de iniciais.

    Props:
      $team   — instância de Team, LeagueTeam ou CompetitionTeam
                (o componente resolve o badge automaticamente via relacionamentos)
      $size   — 'xs' | 'sm' | 'md' | 'lg' | 'xl'  (default: 'sm')
      $class  — classes extras no wrapper

    Uso:
      <x-team-badge :team="$team" />
      <x-team-badge :team="$match->homeTeam" size="md" />
      <x-team-badge :team="$leagueTeam" size="lg" />
--}}

@props([
    'team'  => null,
    'size'  => 'sm',
    'class' => '',
])

@php
    $sizeMap = [
        'xs' => ['box' => 'w-5 h-5',   'text' => 'text-[8px]'],
        'sm' => ['box' => 'w-7 h-7',   'text' => 'text-[10px]'],
        'md' => ['box' => 'w-9 h-9',   'text' => 'text-xs'],
        'lg' => ['box' => 'w-12 h-12', 'text' => 'text-sm'],
        'xl' => ['box' => 'w-16 h-16', 'text' => 'text-base'],
    ];

    $dims = $sizeMap[$size] ?? $sizeMap['sm'];
    $name = $team?->name ?? '?';

    // Resolve badge: Team → badge direto
    //                LeagueTeam → team->badge
    //                CompetitionTeam → leagueTeam->team->badge
    $badge = $team?->badge                           // Team
          ?? $team?->team?->badge                    // LeagueTeam (team_id FK)
          ?? $team?->leagueTeam?->team?->badge       // CompetitionTeam
          ?? null;

    // Iniciais para o placeholder (máx 2 letras)
    $words    = array_filter(explode(' ', $name));
    $initials = count($words) >= 2
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
        : mb_strtoupper(mb_substr($name, 0, 2));
@endphp

<div class="shrink-0 {{ $dims['box'] }} {{ $class }}">
    @if ($badge)
        <img
            src="{{ asset($badge) }}"
            alt="{{ $name }}"
            title="{{ $name }}"
            class="w-full h-full object-contain"
            loading="lazy"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
        >
        <div style="display:none"
             class="w-full h-full rounded-full bg-slate-700 flex items-center justify-center {{ $dims['text'] }} font-bold text-slate-400">
            {{ $initials }}
        </div>
    @else
        <div class="w-full h-full rounded-full bg-slate-700 flex items-center justify-center {{ $dims['text'] }} font-bold text-slate-400">
            {{ $initials }}
        </div>
    @endif
</div>

<?php

namespace App\Services;

/**
 * Gera narração em português para os momentos-chave de uma partida simulada.
 *
 * Cada situação possui um pool de templates com variáveis de contexto:
 *   {team}      — time com a posse/que marcou
 *   {opponent}  — time adversário
 *   {minute}    — minuto da jogada (1–90)
 *   {score}     — placar formatado, ex: "2 × 0"
 *
 * O método principal, narrate(), sorteia um template do pool da situação
 * e interpola as variáveis com os dados do contexto passado.
 */
class MatchNarrator
{
    // ── Pools de templates por situação ─────────────────────────────

    private const TEMPLATES = [

        // ── Gol ──────────────────────────────────────────────────────
        'goal' => [
            "⚽ GOOOOL! {minute}' — {team} balança as redes! Que finalização! {score}",
            "⚽ É GOL! {minute}' — Construção linda e conclusão perfeita de {team}! {score}",
            "⚽ GOOOOOL! {minute}' — {team} não perdoa e faz estufar as redes! {score}",
            "⚽ {minute}' — GOOOOOL DE {team}! A pressão surte efeito e o gol sai! {score}",
            "⚽ GOOOOOL! {minute}' — Finalização no ângulo, sem chances para o goleiro! {score}",
            "⚽ {minute}' — QUE GOL! {team} marca em momento crucial da partida! {score}",
            "⚽ GOOOOOL! {minute}' — {team} aproveita o espaço e não desperdiça! {score}",
            "⚽ {minute}' — É GOL! {team} vira o placar com categoria! {score}",
            "⚽ GOOOOOL! {minute}' — Que chute! A bola foi direto para o canto! {score}",
            "⚽ {minute}' — GOOOOOL! A jogada coletiva termina com {team} na frente! {score}",
        ],

        // ── Grande defesa ─────────────────────────────────────────────
        'shot_saved' => [
            "🧤 {minute}' — QUE DEFESA! {team} chegou com tudo, mas o goleiro de {opponent} voa e salva!",
            "🧤 {minute}' — INCRÍVEL! O arqueiro de {opponent} espalma com a ponta dos dedos!",
            "🧤 {minute}' — Chute forte de {team}, mas o goleiro estava posicionado! Tiro de canto.",
            "🧤 {minute}' — Defesa espetacular de {opponent}! O gol parecia certo.",
            "🧤 {minute}' — {team} chuta com força, o arqueiro de {opponent} mergulha e afasta o perigo!",
            "🧤 {minute}' — Que reflexo! O goleiro de {opponent} reage rápido e evita o gol de {team}.",
            "🧤 {minute}' — {team} finaliza em cima da trave, mas o goleiro já estava lá!",
            "🧤 {minute}' — Grande jogada de {team}, mas encontrou resistência entre as traves de {opponent}!",
        ],

        // ── Chute para fora ───────────────────────────────────────────
        'shot_missed' => [
            "💨 {minute}' — {team} finaliza, mas a bola sai à direita das traves. Que desperdício!",
            "💨 {minute}' — Quase! {team} perdeu grande oportunidade. A bola passou raspando.",
            "💨 {minute}' — Finalização de {team} vai por cima do gol. Chance desperdiçada!",
            "💨 {minute}' — {team} chegou na área, mas errou o alvo. Que pena!",
            "💨 {minute}' — Chute fraco de {team}. O goleiro de {opponent} segura sem dificuldade.",
            "💨 {minute}' — {team} tentou surpreender, mas a bola foi sem direção.",
        ],

        // ── Avanço ao setor de ataque ─────────────────────────────────
        'attack_approach' => [
            "🔥 {minute}' — {team} chega com perigo na área de {opponent}. Pressão crescente!",
            "🔥 {minute}' — Boa jogada de {team}, que avança pelo campo adversário.",
            "🔥 {minute}' — {team} se infiltra pela defesa de {opponent}. Situação perigosa!",
            "🔥 {minute}' — {team} domina o setor de ataque e pressiona a saída de {opponent}.",
            "🔥 {minute}' — Jogada envolvente de {team} que chega ao terço final do campo!",
            "🔥 {minute}' — {team} entra na área adversária. Tensão no estádio!",
        ],

        // ── Perda perigosa de bola no ataque ──────────────────────────
        'possession_lost_attack' => [
            "⚡ {minute}' — {opponent} recupera a bola na entrada da área! Contra-ataque à vista!",
            "⚡ {minute}' — {team} perde a posse em posição avançada. Perigo para o ataque!",
            "⚡ {minute}' — Roubo de bola de {opponent} no campo de {team}! Transição rápida!",
            "⚡ {minute}' — {team} desperdiça a posse na zona de criação. {opponent} vai ao contra-ataque.",
            "⚡ {minute}' — Bola perdida por {team} no ataque. {opponent} sai jogando em velocidade.",
            "⚡ {minute}' — Recuperação de {opponent} em posição avançada! A defesa de {team} precisa se organizar.",
        ],

        // ── Contra-ataque ─────────────────────────────────────────────
        'counter_attack' => [
            "💨 {minute}' — CONTRA-ATAQUE de {team}! Bola recuperada e transição fulminante!",
            "💨 {minute}' — {team} rouba a bola no campo adversário e parte em velocidade!",
            "💨 {minute}' — Virada rápida! {team} sai em contra-ataque com espaço pela frente.",
            "💨 {minute}' — Interceptação de {team} e já está em disparada! Olha o espaço!",
            "💨 {minute}' — {opponent} perde a bola e {team} parte em velocidade pelo campo!",
            "💨 {minute}' — Recuperação e transição relâmpago! {team} leva perigo ao ataque!",
        ],

        // ── Disputa no meio-campo ─────────────────────────────────────
        'midfield_battle' => [
            "⚔️  {minute}' — Disputa intensa no meio-campo. {team} tenta encontrar o espaço.",
            "⚔️  {minute}' — {team} usa o meio-campo para construir a jogada. Fase de elaboração.",
            "⚔️  {minute}' — Batalha no centro do campo! {team} tenta organizar o jogo.",
            "⚔️  {minute}' — Transição pelo meio-campo de {team}. Jogo aberto no centro.",
            "⚔️  {minute}' — {team} movimenta a bola pelo meio e espera o espaço aparecer.",
        ],

        // ── Pressão final (perdendo) ──────────────────────────────────
        'late_pressure' => [
            "🔔 {minute}' — {team} parte com tudo em busca do gol! O jogo está aberto!",
            "🔔 {minute}' — Pressão total de {team}! Falta pouco e precisam marcar!",
            "🔔 {minute}' — {team} joga com desespero no ataque. Tudo ou nada nos momentos finais!",
            "🔔 {minute}' — Nos acréscimos, {team} não para de pressionar. {opponent} segura como pode!",
            "🔔 {minute}' — É hora ou nunca para {team}! O ataque é constante e sem trégua.",
        ],

        // ── Segurando o resultado (ganhando) ──────────────────────────
        'holding_lead' => [
            "🛡️  {minute}' — {team} administra a vantagem com categoria. O relógio corre a favor.",
            "🛡️  {minute}' — {team} toca a bola com calma, esgotando os minutos restantes.",
            "🛡️  {minute}' — Inteligência tática de {team}, que mantém a posse sem arriscar.",
            "🛡️  {minute}' — {team} faz a bola circular e espera o apito final.",
        ],
    ];

    // ── API pública ──────────────────────────────────────────────────

    /**
     * Sorteia e interpola um template para a situação informada.
     *
     * @param array{
     *   team: string,
     *   opponent: string,
     *   minute: int,
     *   home_score: int,
     *   away_score: int
     * } $context
     */
    public function narrate(string $situation, array $context): string
    {
        $pool = self::TEMPLATES[$situation] ?? null;

        if (! $pool) {
            return '';
        }

        $template = $pool[array_rand($pool)];

        return $this->interpolate($template, $context);
    }

    /**
     * Determina se uma jogada de avanço de setor deve ser narrada.
     *
     * Regras de filtragem para "momentos-chave":
     *   - Setor de ataque do possuidor (4-5 home / 1-2 away): 70 % de chance
     *   - Meio-campo (setor 3): 25 % de chance
     *   - Setores defensivos: nunca
     */
    public function shouldNarrateAdvance(int $sector, string $possession): bool
    {
        $attackSector = $possession === 'home' ? $sector >= 4 : $sector <= 2;
        $midfield     = $sector === 3;

        if ($attackSector) {
            return random_int(1, 100) <= 70;
        }

        if ($midfield) {
            return random_int(1, 100) <= 25;
        }

        return false;
    }

    /**
     * Determina se uma perda de posse deve ser narrada.
     *
     * Só narrado quando o time perde a bola no campo ofensivo
     * (situação mais perigosa e interessante para o leitor).
     */
    public function shouldNarrateLoss(int $sector, string $losingTeam): bool
    {
        $lostInAttack = $losingTeam === 'home' ? $sector >= 4 : $sector <= 2;

        return $lostInAttack && random_int(1, 100) <= 75;
    }

    /**
     * Determina se é um contra-ataque: o novo possuidor ganhou a bola
     * no campo ofensivo do adversário.
     */
    public function isCounterAttack(int $sector, string $newPossession): bool
    {
        return $newPossession === 'home' ? $sector >= 4 : $sector <= 2;
    }

    // ── Internos ─────────────────────────────────────────────────────

    private function interpolate(string $template, array $ctx): string
    {
        $score = $ctx['home_score'] . ' × ' . $ctx['away_score'];

        return strtr($template, [
            '{team}'     => $ctx['team'],
            '{opponent}' => $ctx['opponent'],
            '{minute}'   => $ctx['minute'],
            '{score}'    => $score,
        ]);
    }
}

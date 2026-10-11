<?php
require_once __DIR__ . '/partite_schema.php';

function diffidati_event_label(array $event): string {
    $phase = strtoupper(trim((string)($event['fase'] ?? '')));
    if (in_array($phase, ['GOLD', 'SILVER', 'BRONZO'], true)) {
        $round = strtoupper(trim((string)($event['fase_round'] ?? '')));
        if ($round === '') {
            // Stessa codifica dei turni storici usata dal calendario dell'app.
            $round = [1 => 'FINALE', 2 => 'SEMIFINALE', 3 => 'QUARTI', 4 => 'OTTAVI',
                5 => 'SEDICESIMI', 6 => 'TRENTADUESIMI'][(int)($event['giornata'] ?? 0)] ?? '';
        }
        return $round !== '' ? $round . ' ' . $phase : 'COPPA ' . $phase;
    }
    return isset($event['giornata']) ? 'Giornata ' . $event['giornata'] : 'Giornata non indicata';
}

// Gli eventi devono essere passati in ordine cronologico.
function diffidati_apply_event(array $giornate, array $event, bool $labels = false): array {
    if ((int)($event['cartellino_rosso'] ?? 0) > 0) return [];
    $gialli = (int)($event['cartellino_giallo'] ?? 0);
    for ($i = 0; $i < $gialli; $i++) {
        $giornate[] = $labels ? diffidati_event_label($event) : ($event['giornata'] ?? null);
        if (count($giornate) >= 3) $giornate = [];
    }
    return $giornate;
}

function diffidati_fetch(mysqli $conn, string $torneo, int $giocatoreId = 0, int $squadraId = 0): array {
    $teamExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
    $resolved = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');
    $sql = "SELECT pg.giocatore_id, s.id AS squadra_id, g.nome, g.cognome,
                   s.nome AS squadra, p.giornata, p.fase, p.fase_round, pg.cartellino_giallo, pg.cartellino_rosso
            FROM partita_giocatore pg
            JOIN partite p ON p.id = pg.partita_id
            JOIN giocatori g ON g.id = pg.giocatore_id
            JOIN squadre s ON s.id = {$resolved}
            JOIN squadre_giocatori sg ON sg.giocatore_id = g.id AND sg.squadra_id = s.id
            WHERE p.torneo = ? AND (? = 0 OR g.id = ?) AND (? = 0 OR s.id = ?)
            ORDER BY p.data_partita, p.ora_partita, p.id, pg.id";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Impossibile caricare i diffidati');
    $stmt->bind_param('siiii', $torneo, $giocatoreId, $giocatoreId, $squadraId, $squadraId);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Impossibile caricare i diffidati'); }
    $players = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = $row['giocatore_id'] . ':' . $row['squadra_id'];
        if (!isset($players[$key])) {
            $players[$key] = ['giocatore_id' => (int)$row['giocatore_id'], 'squadra_id' => (int)$row['squadra_id'],
                'nome' => $row['nome'], 'cognome' => $row['cognome'], 'squadra' => $row['squadra'], 'giornate' => [], 'ammonizioni' => []];
        }
        $players[$key]['giornate'] = diffidati_apply_event($players[$key]['giornate'], $row);
        $players[$key]['ammonizioni'] = diffidati_apply_event($players[$key]['ammonizioni'], $row, true);
    }
    $stmt->close();
    $players = array_values(array_filter($players, static fn(array $player): bool => count($player['giornate']) >= 2));
    usort($players, static fn(array $a, array $b): int => strcasecmp($a['squadra'], $b['squadra']) ?: strcasecmp($a['cognome'] . $a['nome'], $b['cognome'] . $b['nome']));
    return $players;
}

<?php
require_once __DIR__ . '/partite_schema.php';

// Gli eventi devono essere passati in ordine cronologico.
function diffidati_apply_event(array $giornate, array $event): array {
    if ((int)($event['cartellino_rosso'] ?? 0) > 0) return [];
    $gialli = (int)($event['cartellino_giallo'] ?? 0);
    for ($i = 0; $i < $gialli; $i++) {
        $giornate[] = $event['giornata'] ?? null;
        if (count($giornate) >= 3) $giornate = [];
    }
    return $giornate;
}

function diffidati_fetch(mysqli $conn, string $torneo, int $giocatoreId = 0, int $squadraId = 0): array {
    $teamExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
    $resolved = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');
    $sql = "SELECT pg.giocatore_id, s.id AS squadra_id, g.nome, g.cognome,
                   s.nome AS squadra, p.giornata, pg.cartellino_giallo, pg.cartellino_rosso
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
                'nome' => $row['nome'], 'cognome' => $row['cognome'], 'squadra' => $row['squadra'], 'giornate' => []];
        }
        $players[$key]['giornate'] = diffidati_apply_event($players[$key]['giornate'], $row);
    }
    $stmt->close();
    $players = array_values(array_filter($players, static fn(array $player): bool => count($player['giornate']) >= 2));
    usort($players, static fn(array $a, array $b): int => strcasecmp($a['squadra'], $b['squadra']) ?: strcasecmp($a['cognome'] . $a['nome'], $b['cognome'] . $b['nome']));
    return $players;
}

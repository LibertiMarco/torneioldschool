<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

// --- CONNESSIONE DATABASE TRAMITE FILE ESTERNO ---
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/api_cache.php';
require_once __DIR__ . '/../includi/partite_schema.php';
require_once __DIR__ . '/../includi/torneo_phase_rules.php';


$torneo=$_GET['torneo']??''; $squadra=$_GET['squadra']??'';
if(!$torneo || !$squadra){ echo json_encode(['error'=>'Parametri mancanti']); exit; }

if (!function_exists('partita_giocatore_resolved_team_expr')) {
    echo json_encode(['error' => 'Helper squadra partita non disponibile']);
    exit;
}

$cacheKey = tos_api_cache_build_key('get_rosa', [
    'torneo' => $torneo,
    'squadra' => $squadra,
]);
$cachedPayload = tos_api_cache_read($cacheKey, 60);
if ($cachedPayload !== null) {
    echo $cachedPayload;
    exit;
}

$phaseClause = torneo_stats_team_phase_clause($conn, $torneo, 'p.fase');
$teamIdExpr = partita_giocatore_team_id_expr($conn, 'pg.squadra_id');
$resolvedTeamExpr = partita_giocatore_resolved_team_expr('pg.giocatore_id', $teamIdExpr, 'p.torneo', 'p.squadra_casa', 'p.squadra_ospite');
$sql = "
    SELECT 
        g.id, g.nome, g.cognome, g.ruolo,
        sg.ruolo AS ruolo_squadra,
        sg.is_captain,
        COALESCE(agg.presenze, 0) AS presenze,
        COALESCE(agg.reti, 0) AS reti,
        COALESCE(agg.assist, 0) AS assist,
        COALESCE(agg.gialli, 0) AS gialli,
        COALESCE(agg.rossi, 0) AS rossi,
        CASE
            WHEN COALESCE(agg.num_voti, 0) > 0 THEN ROUND(agg.somma_voti / agg.num_voti, 2)
            ELSE NULL
        END AS media_voti,
        COALESCE(sg.foto, g.foto) AS foto,
        s.logo AS logo_squadra
    FROM squadre s
    JOIN squadre_giocatori sg ON sg.squadra_id = s.id
    JOIN giocatori g ON g.id = sg.giocatore_id
    LEFT JOIN (
        SELECT
            src.giocatore_id,
            src.squadra_ref,
            COALESCE(SUM(CASE WHEN src.presenza = 1 THEN 1 ELSE 0 END), 0) AS presenze,
            COALESCE(SUM(src.goal), 0) AS reti,
            COALESCE(SUM(src.assist), 0) AS assist,
            COALESCE(SUM(src.cartellino_giallo), 0) AS gialli,
            COALESCE(SUM(src.cartellino_rosso), 0) AS rossi,
            SUM(CASE WHEN src.voto IS NOT NULL THEN src.voto ELSE 0 END) AS somma_voti,
            SUM(CASE WHEN src.voto IS NOT NULL THEN 1 ELSE 0 END) AS num_voti
        FROM (
            SELECT
                pg.giocatore_id,
                {$resolvedTeamExpr} AS squadra_ref,
                pg.presenza,
                pg.goal,
                pg.assist,
                pg.cartellino_giallo,
                pg.cartellino_rosso,
                pg.voto
            FROM partita_giocatore pg
            JOIN partite p ON p.id = pg.partita_id
            WHERE p.torneo = ?
              $phaseClause
        ) src
        GROUP BY src.giocatore_id, src.squadra_ref
    ) agg ON agg.giocatore_id = g.id AND agg.squadra_ref = s.id
    WHERE s.torneo = ? AND s.nome = ?
    ORDER BY g.cognome, g.nome
";
$st=$conn->prepare($sql);
if (!$st) {
    echo json_encode(['error' => 'Errore query rosa']);
    exit;
}
$st->bind_param("sss", $torneo, $torneo, $squadra);
$st->execute();
$res=$st->get_result();
$out=[];
while($r=$res->fetch_assoc()) {
    $out[] = $r;
}
$st->close();
$payload = json_encode($out, JSON_UNESCAPED_UNICODE);
if ($payload !== false) {
    tos_api_cache_write($cacheKey, $payload);
    echo $payload;
} else {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Ricalcola le statistiche aggregate dei giocatori e delle associazioni squadra/giocatore
 * usando la logica corrente delle fasi torneo.
 *
 * Uso browser:
 *   /api/script/ricalcola_statistiche_giocatori.php
 *
 * Uso CLI:
 *   php api/script/ricalcola_statistiche_giocatori.php yes
 */

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../../includi/admin_guard.php';
}

require_once __DIR__ . '/../../includi/db.php';
require_once __DIR__ . '/../../includi/torneo_phase_rules.php';

@set_time_limit(0);
@ini_set('max_execution_time', '0');
ignore_user_abort(true);

function stats_recalc_cli_arg(int $index, array $argv): ?string
{
    return $argv[$index] ?? null;
}

function stats_recalc_overview(mysqli $conn): array
{
    $overview = [
        'giocatori' => 0,
        'associazioni' => 0,
        'associazioni_campionato_con_finali' => 0,
    ];

    $resPlayers = $conn->query("SELECT COUNT(*) AS totale FROM giocatori");
    if ($resPlayers) {
        $row = $resPlayers->fetch_assoc();
        $overview['giocatori'] = (int)($row['totale'] ?? 0);
        $resPlayers->free();
    }

    $resAssoc = $conn->query("SELECT COUNT(*) AS totale FROM squadre_giocatori");
    if ($resAssoc) {
        $row = $resAssoc->fetch_assoc();
        $overview['associazioni'] = (int)($row['totale'] ?? 0);
        $resAssoc->free();
    }

    if (!torneo_stats_table_has_column($conn, 'tornei', 'config')) {
        return $overview;
    }

    $sql = "
        SELECT COUNT(*) AS totale
        FROM (
            SELECT sg.id
            FROM squadre_giocatori sg
            JOIN squadre s ON s.id = sg.squadra_id
            JOIN tornei t
              ON t.filetorneo IN (CONCAT(s.torneo, '.php'), CONCAT(s.torneo, '.html'))
              OR t.nome = s.torneo
            JOIN partita_giocatore pg ON pg.giocatore_id = sg.giocatore_id
            JOIN partite p
              ON p.id = pg.partita_id
             AND p.torneo = s.torneo
             AND (p.squadra_casa = s.nome OR p.squadra_ospite = s.nome)
            WHERE p.giocata = 1
              AND JSON_UNQUOTE(JSON_EXTRACT(t.config, '$.formato')) = 'campionato'
              AND " . torneo_stats_normalized_phase_expr('p.fase') . " <> 'REGULAR'
            GROUP BY sg.id
        ) impacted
    ";
    $resImpacted = $conn->query($sql);
    if ($resImpacted) {
        $row = $resImpacted->fetch_assoc();
        $overview['associazioni_campionato_con_finali'] = (int)($row['totale'] ?? 0);
        $resImpacted->free();
    }

    return $overview;
}

function stats_recalc_run(mysqli $conn): array
{
    $conn->begin_transaction();
    try {
        $result = torneo_stats_rebuild_all_player_aggregates($conn);
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function stats_recalc_render_form(mysqli $conn, string $message = '', string $messageType = 'info'): void
{
    $overview = stats_recalc_overview($conn);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ricalcola Statistiche Giocatori</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 760px; margin: 40px auto; padding: 20px; background: #f6f8fc; color: #15293e; }
            .card { background: #fff; border: 1px solid #dfe6f0; border-radius: 14px; padding: 20px; box-shadow: 0 10px 24px rgba(15,31,51,0.08); }
            h1 { margin: 0 0 10px; font-size: 24px; }
            p { line-height: 1.5; }
            .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 18px 0; }
            .meta-card { border: 1px solid #e4ebf3; border-radius: 12px; padding: 12px; background: #fbfcff; }
            .meta-card strong { display: block; font-size: 22px; margin-top: 4px; }
            .msg { margin: 14px 0; padding: 12px 14px; border-radius: 10px; font-weight: 600; }
            .msg.info { background: #e8f0ff; border: 1px solid #c7d6ff; color: #1b3970; }
            .msg.success { background: #e8f8ef; border: 1px solid #bfe6cd; color: #165a2d; }
            .msg.error { background: #fff1f1; border: 1px solid #f0c4c4; color: #8b2020; }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
            button, a.btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; border-radius: 10px; border: none; text-decoration: none; font-weight: 700; cursor: pointer; }
            button { background: linear-gradient(135deg, #1f3f63, #2c507f); color: #fff; }
            a.btn { background: #edf2f8; color: #15293e; border: 1px solid #d6dee9; }
            code { background: #eef3f8; padding: 2px 6px; border-radius: 6px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Ricalcola Statistiche Giocatori</h1>
            <p>Questa pagina ricalcola i totali in <code>giocatori</code> e <code>squadre_giocatori</code> usando la logica corretta delle fasi: nei tornei campionato somma anche le fasi finali nelle statistiche personali per squadra.</p>

            <?php if ($message !== ''): ?>
                <div class="msg <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="meta">
                <div class="meta-card">
                    Record giocatori
                    <strong><?= (int)$overview['giocatori'] ?></strong>
                </div>
                <div class="meta-card">
                    Associazioni squadra/giocatore
                    <strong><?= (int)$overview['associazioni'] ?></strong>
                </div>
                <div class="meta-card">
                    Associazioni campionato con finali
                    <strong><?= (int)$overview['associazioni_campionato_con_finali'] ?></strong>
                </div>
            </div>

            <form method="post">
                <?= csrf_field('stats_recalc') ?>
                <input type="hidden" name="confirm" value="yes">
                <div class="actions">
                    <button type="submit">Esegui ricalcolo</button>
                    <a class="btn" href="<?= htmlspecialchars(login_with_base_path('/admin_dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">Torna alla dashboard</a>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (PHP_SAPI === 'cli') {
    $confirm = strtolower(trim((string)stats_recalc_cli_arg(1, $argv ?? [])));
    if ($confirm !== 'yes') {
        exit("Conferma richiesta. Usa: php api/script/ricalcola_statistiche_giocatori.php yes\n");
    }

    try {
        $result = stats_recalc_run($conn);
        echo "Ricalcolo completato.\n";
        echo "Giocatori aggiornati: " . (int)($result['giocatori_globali'] ?? 0) . "\n";
        echo "Associazioni ricalcolate: " . (int)($result['associazioni_squadra'] ?? 0) . "\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Errore durante il ricalcolo: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stats_recalc_render_form($conn);
}

csrf_require('stats_recalc');
$confirm = strtolower(trim((string)($_POST['confirm'] ?? '')));
if ($confirm !== 'yes') {
    stats_recalc_render_form($conn, 'Conferma mancante.', 'error');
}

try {
    $result = stats_recalc_run($conn);
    $message = sprintf(
        'Ricalcolo completato: %d giocatori aggiornati, %d associazioni squadra/giocatore ricalcolate.',
        (int)($result['giocatori_globali'] ?? 0),
        (int)($result['associazioni_squadra'] ?? 0)
    );
    stats_recalc_render_form($conn, $message, 'success');
} catch (Throwable $e) {
    stats_recalc_render_form($conn, 'Errore durante il ricalcolo: ' . $e->getMessage(), 'error');
}

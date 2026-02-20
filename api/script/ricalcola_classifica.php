<?php
require __DIR__ . '/../../includi/db.php';

/**
 * Rebuilds the standings for a tournament using only matches marked as "giocata = 1"
 * and in regular phase. Use via browser or CLI:
 *   - Browser: /api/script/ricalcola_classifica.php?torneo=slug&confirm=yes
 *   - CLI: php api/script/ricalcola_classifica.php slug yes
 */

function arg(string $name, int $cliIndex, array $argv): ?string {
  if (PHP_SAPI === 'cli') {
    return $argv[$cliIndex] ?? null;
  }
  return $_GET[$name] ?? null;
}

function getTornei(mysqli $conn): array {
  $rows = [];
  $res = $conn->query("SELECT nome, filetorneo FROM tornei ORDER BY nome ASC");
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $slug = preg_replace('/\.(html?|php)$/i', '', $r['filetorneo'] ?? '');
      $rows[] = [
        'nome' => $r['nome'] ?: $slug,
        'slug' => $slug,
      ];
    }
  }
  return $rows;
}

function renderForm(mysqli $conn, string $message = ''): void {
  $tornei = getTornei($conn);
  header('Content-Type: text/html; charset=utf-8');
  ?>
  <!DOCTYPE html>
  <html lang="it">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricalcola classifica</title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 520px; margin: 40px auto; padding: 20px; background: #f7f9fc; color: #13283c; }
      .card { background: #fff; border: 1px solid #e1e8f0; border-radius: 12px; padding: 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
      h1 { margin: 0 0 14px; font-size: 20px; }
      label { font-weight: 700; display: block; margin-bottom: 6px; }
      select { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #cdd6e0; background: #fafbff; }
      button { margin-top: 14px; padding: 12px 16px; background: linear-gradient(135deg, #1f3f63, #2c507f); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
      .msg { margin-bottom: 10px; padding: 10px; border-radius: 8px; font-weight: 600; }
      .msg.info { background: #e8f0ff; border: 1px solid #c5d5ff; color: #1a3060; }
      .empty { color: #8a97a8; }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Ricalcola classifica</h1>
      <?php if ($message): ?><div class="msg info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if (empty($tornei)): ?>
        <p class="empty">Nessun torneo trovato.</p>
      <?php else: ?>
        <form method="get">
          <label for="torneo">Seleziona torneo</label>
          <select id="torneo" name="torneo" required>
            <option value="">-- scegli --</option>
            <?php foreach ($tornei as $t): ?>
              <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="confirm" value="yes">
          <button type="submit">Ricalcola</button>
        </form>
      <?php endif; ?>
    </div>
  </body>
  </html>
  <?php
  exit;
}

$torneo = trim((string)arg('torneo', 1, $argv ?? []));
$confirm = trim((string)arg('confirm', 2, $argv ?? []));

if (PHP_SAPI !== 'cli' && ($torneo === '' || strtolower($confirm) !== 'yes')) {
  renderForm($conn, 'Seleziona un torneo e conferma per ricalcolare.');
}

if (PHP_SAPI === 'cli' && $torneo === '') {
  exit("Errore: specifica il torneo con ?torneo=slug oppure CLI: php ricalcola_classifica.php torneo_slug [yes]\n");
}

if (PHP_SAPI === 'cli' && strtolower($confirm) !== 'yes') {
  exit("Conferma richiesta. Secondo parametro CLI 'yes'.\n");
}

function reset_classifica(mysqli $conn, string $torneo): void {
  $stmt = $conn->prepare("
    UPDATE squadre
    SET giocate = 0, vinte = 0, pareggiate = 0, perse = 0,
        punti = 0, gol_fatti = 0, gol_subiti = 0, differenza_reti = 0
    WHERE torneo = ?
  ");
  if ($stmt) {
    $stmt->bind_param('s', $torneo);
    $stmt->execute();
    $stmt->close();
  }
}

function applica_risultato_classifica(mysqli $conn, string $torneo, string $squadra, int $gf, int $gs): void {
  $vittoria = $gf > $gs ? 1 : 0;
  $pareggio = $gf === $gs ? 1 : 0;
  $sconfitta = $gf < $gs ? 1 : 0;
  $punti = $vittoria ? 3 : ($pareggio ? 1 : 0);
  $stmt = $conn->prepare("
    UPDATE squadre
    SET giocate = giocate + 1,
        vinte = vinte + ?,
        pareggiate = pareggiate + ?,
        perse = perse + ?,
        punti = punti + ?,
        gol_fatti = gol_fatti + ?,
        gol_subiti = gol_subiti + ?,
        differenza_reti = gol_fatti - gol_subiti
    WHERE torneo = ? AND nome = ?
    LIMIT 1
  ");
  if ($stmt) {
    $stmt->bind_param(
      'iiiiiiis',
      $vittoria,
      $pareggio,
      $sconfitta,
      $punti,
      $gf,
      $gs,
      $torneo,
      $squadra
    );
    $stmt->execute();
    $stmt->close();
  }
}

function ricostruisci_classifica_da_partite(mysqli $conn, string $torneo): void {
  reset_classifica($conn, $torneo);
  $sel = $conn->prepare("
    SELECT p.squadra_casa,
           p.squadra_ospite,
           COALESCE(p.gol_casa,0)   AS gol_casa,
           COALESCE(p.gol_ospite,0) AS gol_ospite
    FROM partite p
    INNER JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = ?
    INNER JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = ?
    WHERE p.torneo = ?
      AND p.giocata = 1
      AND UPPER(
            CASE
              WHEN TRIM(COALESCE(p.fase, '')) IN ('', 'GIRONE') THEN 'REGULAR'
              ELSE TRIM(COALESCE(p.fase, ''))
            END
          ) = 'REGULAR'
  ");
  if (!$sel) return;
  $sel->bind_param('sss', $torneo, $torneo, $torneo);
  if ($sel->execute()) {
    $res = $sel->get_result();
    while ($row = $res->fetch_assoc()) {
      applica_risultato_classifica($conn, $torneo, $row['squadra_casa'], (int)$row['gol_casa'], (int)$row['gol_ospite']);
      applica_risultato_classifica($conn, $torneo, $row['squadra_ospite'], (int)$row['gol_ospite'], (int)$row['gol_casa']);
    }
  }
  $sel->close();
}

ricostruisci_classifica_da_partite($conn, $torneo);
echo "Classifica ricalcolata per il torneo '{$torneo}' usando solo partite con giocata = 1 (fase regular).\n";

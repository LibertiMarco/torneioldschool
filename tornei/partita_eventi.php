<?php
require_once __DIR__ . '/../includi/seo.php';
require_once __DIR__ . '/../includi/db.php';

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$torneo = trim($_GET['torneo'] ?? '');
$baseUrl = seo_base_url();

$matchUrl = $baseUrl . '/tornei/partita_eventi.php';
if ($matchId > 0) {
    $matchUrl .= '?id=' . $matchId . ($torneo ? '&torneo=' . urlencode($torneo) : '');
}

$matchSeo = [
    'title' => 'Statistiche partita - Tornei Old School',
    'description' => 'Dettaglio eventi, marcatori e video della partita.',
    'url' => $matchUrl,
    'canonical' => $matchUrl,
    'type' => 'article',
    'image' => $baseUrl . '/img/logo_old_school.png',
];
$matchBreadcrumbs = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Tornei', 'url' => $baseUrl . '/tornei.php'],
    ['name' => 'Partita', 'url' => $matchUrl],
]);
$eventSchema = [];

if ($matchId > 0) {
    if ($torneo === '') {
        $lookup = $conn->prepare("SELECT torneo FROM partite WHERE id = ? LIMIT 1");
        if ($lookup) {
            $lookup->bind_param("i", $matchId);
            if ($lookup->execute()) {
                $result = $lookup->get_result();
                $torneo = $result->fetch_assoc()['torneo'] ?? $torneo;
            }
            $lookup->close();
        }
    }

    $query = "SELECT p.*, sc.logo AS logo_lookup_casa, so.logo AS logo_lookup_ospite
              FROM partite p
              LEFT JOIN squadre sc ON sc.nome = p.squadra_casa AND sc.torneo = p.torneo
              LEFT JOIN squadre so ON so.nome = p.squadra_ospite AND so.torneo = p.torneo
              WHERE p.id = ?";
    $types = "i";
    $params = [$matchId];
    if ($torneo !== '') {
        $query .= " AND p.torneo = ?";
        $types .= "s";
        $params[] = $torneo;
    }
    $query .= " LIMIT 1";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $home = $row['squadra_casa'] ?? 'Squadra casa';
                $away = $row['squadra_ospite'] ?? 'Squadra ospite';
                $torneoName = $row['torneo'] ?? $torneo;
                $matchTitle = trim($home . ' vs ' . $away . ($torneoName ? ' - ' . $torneoName : ''));
                $campo = trim($row['campo'] ?? '');
                $ora = trim($row['ora_partita'] ?? '');
                $data = $row['data_partita'] ?? '';

                $descParts = [];
                if ($data) {
                    $descParts[] = 'Data ' . $data . ($ora ? ' ' . substr($ora, 0, 5) : '');
                }
                if ($campo) {
                    $descParts[] = 'Campo ' . $campo;
                }

                $imageCandidate = $row['logo_lookup_casa'] ?? $row['logo_lookup_ospite'] ?? '';
                $imageUrl = $imageCandidate ? $baseUrl . '/' . ltrim($imageCandidate, '/') : $matchSeo['image'];

                $matchSeo = [
                    'title' => $matchTitle,
                    'description' => $descParts ? implode(' | ', $descParts) : $matchSeo['description'],
                    'url' => $matchUrl,
                    'canonical' => $matchUrl,
                    'type' => 'article',
                    'image' => $imageUrl,
                ];

                $matchBreadcrumbs = seo_breadcrumb_schema([
                    ['name' => 'Home', 'url' => $baseUrl . '/'],
                    ['name' => 'Tornei', 'url' => $baseUrl . '/tornei.php'],
                    ['name' => $matchTitle, 'url' => $matchUrl],
                ]);

                $isoDate = '';
                if (!empty($data)) {
                    $dateSource = trim($data . ' ' . ($ora !== '' ? $ora : '00:00:00'));
                    $isoDate = date('c', strtotime($dateSource));
                }

                if ($isoDate) {
                    $eventSchema = seo_event_schema([
                        'name' => $matchTitle,
                        'startDate' => $isoDate,
                        'location' => $campo ?: 'Campo da definire',
                        'homeTeam' => $home,
                        'awayTeam' => $away,
                        'description' => $descParts ? implode(' | ', $descParts) : '',
                        'url' => $matchUrl,
                    ]);
                }
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php render_seo_tags($matchSeo); ?>
  <?php render_jsonld($matchBreadcrumbs); ?>
  <?php render_jsonld($eventSchema); ?>
  <!-- stesso CSS della pagina principale -->
  <link rel="stylesheet" href="../style.css" />
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Oswald:wght@500&display=swap" rel="stylesheet">
</head>

<body>

  <!-- HEADER -->
  <div id="header-container"></div>

  <!-- CONTENUTO -->
  <main class="content" style="margin-top:8px; padding-top:6px;">
    <button id="btnBack" onclick="history.back()">⟵</button>
    <h1 class="titolo">Statistiche Partita</h1>

    <!-- ✅ Qui iniettiamo la STESSA match-card del calendario -->
    <div id="partitaContainer" style="margin-bottom:20px;"></div>

    <!-- ✅ Riepilogo eventi -->
    <div id="riepilogoEventi" style="margin-bottom:20px;"></div>

    <!-- ✅ Cards giocatori -->
    <div id="eventiGiocatori" class="eventi-giocatori-grid"></div>
  </main>

  <!-- FOOTER -->
  <div id="footer-container"></div>

  <!-- SCRIPT: HEADER -->
  <script src="/includi/header-interactions.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      fetch("/includi/header.php")
        .then(r => r.text())
                .then(html => {
          document.getElementById("header-container").innerHTML = html;
          initHeaderInteractions();
        })
        .catch(e => console.error("Errore header:", e));
    });
  </script>

  <!-- SCRIPT: FOOTER -->
  <script>
    fetch("/includi/footer.html")
      .then(r => r.text())
      .then(html => { document.getElementById("footer-container").innerHTML = html; })
      .catch(e => console.error("Errore footer:", e));
  </script>

  <!-- SCRIPT: PAGINA -->
  <script src="partita_eventi.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/includi/admin_guard.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/fanta_old_school.php';

$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'fanta-old-school') ? 'fanta-old-school' : 'strumenti';
$referralOverview = fanta_old_school_fetch_admin_overview($conn);
$totalReferralLeads = 0;
$activeReferrers = 0;

foreach ($referralOverview as $row) {
    $count = (int)($row['lead_count'] ?? 0);
    $totalReferralLeads += $count;
    if ($count > 0) {
        $activeReferrers++;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-VZ982XSRRN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-VZ982XSRRN');
  </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard Amministratore</title>
    <link rel="stylesheet" href="/style.min.css?v=20251126">
    <link rel="icon" type="image/png" href="/img/logo_old_school.png">
    <link rel="apple-touch-icon" href="/img/logo_old_school.png">
    <style>
      .admin-tab-nav {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin: 22px 0 18px;
      }
      .admin-tab-btn {
        border: 1px solid #d6e0ea;
        background: #fff;
        color: #15293e;
        border-radius: 999px;
        padding: 11px 16px;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
      }
      .admin-tab-btn:hover {
        transform: translateY(-1px);
      }
      .admin-tab-btn.is-active {
        background: #15293e;
        color: #fff;
        border-color: #15293e;
      }
      .admin-tab-panel {
        display: none;
      }
      .admin-tab-panel.is-active {
        display: block;
      }
      .referral-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 18px;
      }
      .referral-summary-card {
        background: linear-gradient(180deg, #f8fbff 0%, #eef5fb 100%);
        border: 1px solid #d9e5f1;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 16px 30px rgba(15, 31, 51, 0.07);
      }
      .referral-summary-card strong {
        display: block;
        font-size: 2rem;
        color: #15293e;
        line-height: 1;
        margin-bottom: 8px;
      }
      .referral-summary-card span {
        color: #4c5b71;
        font-weight: 600;
      }
      .referral-table-card {
        padding: 0;
        overflow: hidden;
      }
      .referral-table-head {
        padding: 22px 24px 10px;
      }
      .referral-table-head h3 {
        margin: 0 0 8px;
        color: #15293e;
      }
      .referral-table-head p {
        margin: 0;
        color: #4c5b71;
      }
      .referral-table-wrap {
        overflow-x: auto;
        padding: 0 24px 24px;
      }
      .referral-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
      }
      .referral-table th,
      .referral-table td {
        text-align: left;
        vertical-align: top;
        padding: 14px 12px;
        border-bottom: 1px solid #e2e8f0;
      }
      .referral-table th {
        color: #15293e;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }
      .referral-table td {
        color: #334155;
      }
      .referral-code {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #edf2f7;
        color: #15293e;
        font-weight: 800;
        font-size: 0.9rem;
      }
      .referral-lead-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 250px;
      }
      .referral-lead-item {
        padding: 10px 12px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
      }
      .referral-lead-item strong {
        display: block;
        color: #15293e;
        margin-bottom: 4px;
      }
      .referral-lead-item span {
        display: block;
        color: #475569;
        font-size: 0.93rem;
        line-height: 1.45;
      }
      .referral-empty {
        color: #64748b;
        font-weight: 600;
      }
    </style>
</head>
<body class="admin-page">
    <?php include __DIR__ . '/includi/header.php'; ?>

    <main class="admin-dashboard">
        <h1 class="admin-title">Pannello Amministratore</h1>

        <div class="admin-tab-nav" role="tablist" aria-label="Sezioni dashboard admin">
            <button
                type="button"
                class="admin-tab-btn <?= $activeTab === 'strumenti' ? 'is-active' : '' ?>"
                data-tab-target="adminTabStrumenti"
                aria-selected="<?= $activeTab === 'strumenti' ? 'true' : 'false' ?>"
            >Strumenti</button>
            <button
                type="button"
                class="admin-tab-btn <?= $activeTab === 'fanta-old-school' ? 'is-active' : '' ?>"
                data-tab-target="adminTabFantaOldSchool"
                aria-selected="<?= $activeTab === 'fanta-old-school' ? 'true' : 'false' ?>"
            >Fanta Old School</button>
        </div>

        <section id="adminTabStrumenti" class="admin-tab-panel <?= $activeTab === 'strumenti' ? 'is-active' : '' ?>">
        <div class="cards-container">
            <div class="admin-card">
                <h3>Gestione Tornei</h3>
                <p>Crea, modifica o elimina tornei esistenti.</p><br>
                <a href="/api/gestione_tornei.php">Gestisci</a>
            </div>

            <div class="admin-card">
                <h3>Gestione Squadre</h3>
                <p>Visualizza e aggiorna le squadre iscritte ai tornei.</p><br>
                <a href="/api/gestione_squadre.php">Vai</a>
            </div>

            <div class="admin-card">
                <h3>Gestione Giocatori</h3>
                <p>Visualizza e aggiorna i giocatori delle squadre.</p><br>
                <a href="/api/gestione_giocatori.php">Vai</a>
            </div>

            <div class="admin-card">
                <h3>Account - Giocatori</h3>
                <p>Associa un account utente al relativo giocatore (solo admin).</p><br>
                <a href="/api/gestione_account_giocatore.php">Abbina</a>
            </div>

            <div class="admin-card">
                <h3>Calendario & Risultati</h3>
                <p>Inserisci o aggiorna date e punteggi dei tornei.</p><br>
                <a href="/api/gestione_partite.php">Apri</a>
            </div>

      <div class="admin-card">
        <h3>Utenti & Iscrizioni</h3>
          <p>Controlla gli utenti registrati e le loro iscrizioni.</p><br>
          <a href="/api/gestione_utenti.php">Visualizza</a>
      </div>

      <div class="admin-card">
        <h3>Funzioni Account</h3>
        <p>Abilita o disabilita Totocalcio, Fantacalcio e altre funzioni per ogni account.</p><br>
        <a href="/api/gestione_funzioni_account.php">Configura</a>
      </div>

      <div class="admin-card">
        <h3>Gestione Totocalcio</h3>
        <p>Seleziona partite dal calendario e gestisci la classifica del Totocalcio.</p><br>
        <a href="/api/gestione_totocalcio.php">Apri</a>
      </div>

      <div class="admin-card">
        <h3>Gestione Fantacalcio</h3>
        <p>Prepara l'area admin del Fantacalcio da sviluppare insieme nelle prossime iterazioni.</p><br>
        <a href="/api/gestione_fantacalcio.php">Apri</a>
      </div>

      <div class="admin-card">
        <h3>Referral Fanta Old School</h3>
        <p>Apri la tab dedicata e controlla quanti invitati ha portato ogni utente.</p><br>
        <a href="<?= htmlspecialchars(login_with_base_path('/admin_dashboard.php?tab=fanta-old-school')) ?>">Apri tab</a>
      </div>

      <div class="admin-card">
        <h3>Gestione Blog</h3>
        <p>Pubblica nuovi articoli e tieni aggiornato il blog.</p><br>
        <a href="/api/gestione_blog.php">Crea articoli</a>
      </div>

      <div class="admin-card">
        <h3>Gestione Staff</h3>
        <p>Aggiungi arbitri, videomaker e altri ruoli dello staff.</p><br>
        <a href="/api/gestione_staff.php">Gestisci</a>
      </div>

      <div class="admin-card">
        <h3>Albo d'oro</h3>
        <p>Inserisci e aggiorna le vincitrici dei tornei.</p><br>
        <a href="/api/gestione_albo.php">Gestisci</a>
      </div>
    </div>
    </section>

    <section id="adminTabFantaOldSchool" class="admin-tab-panel <?= $activeTab === 'fanta-old-school' ? 'is-active' : '' ?>">
      <div class="referral-summary-grid">
        <div class="referral-summary-card">
          <strong><?= $totalReferralLeads ?></strong>
          <span>richieste salvate nella tabella Fanta Old School</span>
        </div>
        <div class="referral-summary-card">
          <strong><?= $activeReferrers ?></strong>
          <span>utenti che hanno gia almeno un invitato</span>
        </div>
        <div class="referral-summary-card">
          <strong><?= count($referralOverview) ?></strong>
          <span>utenti con referral code assegnato</span>
        </div>
      </div>

      <div class="admin-card referral-table-card">
        <div class="referral-table-head">
          <h3>Inviti per utente</h3>
          <p>Qui vedi quante persone ha invitato ogni account e l'elenco completo di nomi, email Leghe FC e data invio.</p>
        </div>

        <div class="referral-table-wrap">
          <table class="referral-table">
            <thead>
              <tr>
                <th>Utente</th>
                <th>Referral code</th>
                <th>Link</th>
                <th>Invitati</th>
                <th>Dettagli persone invitate</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($referralOverview): ?>
                <?php foreach ($referralOverview as $row): ?>
                  <?php
                    $referralCode = (string)($row['referral_code'] ?? '');
                    $leadCount = (int)($row['lead_count'] ?? 0);
                    $referralLink = login_with_base_path('/fantaoldschool') . '?ref=' . rawurlencode($referralCode);
                  ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars((string)($row['label'] ?? 'Utente')) ?></strong><br>
                      <span><?= htmlspecialchars((string)($row['email'] ?? '')) ?></span>
                    </td>
                    <td>
                      <?php if ($referralCode !== ''): ?>
                        <span class="referral-code"><?= htmlspecialchars($referralCode) ?></span>
                      <?php else: ?>
                        <span class="referral-empty">non disponibile</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($referralCode !== ''): ?>
                        <a href="<?= htmlspecialchars($referralLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($referralLink) ?></a>
                      <?php else: ?>
                        <span class="referral-empty">non disponibile</span>
                      <?php endif; ?>
                    </td>
                    <td><strong><?= $leadCount ?></strong></td>
                    <td>
                      <?php if ($leadCount > 0): ?>
                        <div class="referral-lead-list">
                          <?php foreach (($row['leads'] ?? []) as $lead): ?>
                            <div class="referral-lead-item">
                              <strong><?= htmlspecialchars(trim((string)($lead['nome'] ?? '') . ' ' . (string)($lead['cognome'] ?? ''))) ?></strong>
                              <span><?= htmlspecialchars((string)($lead['email_leghe_fc'] ?? '')) ?></span>
                              <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($lead['created_at'] ?? 'now')))) ?></span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <span class="referral-empty">Nessun invitato registrato</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">
                    <span class="referral-empty">Nessun utente disponibile per il riepilogo referral.</span>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <a class="logout-btn" href="index.php">Esci dal pannello</a>
    </main>
    <div id="footer-container"></div>
    <script src="/includi/app.min.js?v=20251220"></script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const tabButtons = document.querySelectorAll(".admin-tab-btn");
        const panels = document.querySelectorAll(".admin-tab-panel");

        tabButtons.forEach(button => {
          button.addEventListener("click", () => {
            const targetId = button.getAttribute("data-tab-target");
            if (!targetId) return;

            tabButtons.forEach(btn => {
              btn.classList.remove("is-active");
              btn.setAttribute("aria-selected", "false");
            });
            panels.forEach(panel => panel.classList.remove("is-active"));

            button.classList.add("is-active");
            button.setAttribute("aria-selected", "true");

            const targetPanel = document.getElementById(targetId);
            if (targetPanel) {
              targetPanel.classList.add("is-active");
            }

            try {
              const nextUrl = new URL(window.location.href);
              if (targetId === "adminTabFantaOldSchool") {
                nextUrl.searchParams.set("tab", "fanta-old-school");
              } else {
                nextUrl.searchParams.delete("tab");
              }
              window.history.replaceState({}, "", nextUrl.toString());
            } catch (err) {}
          });
        });

        fetch("/includi/footer.html")
          .then(r => r.text())
          .then(html => {
            const footer = document.getElementById("footer-container");
            if (footer) footer.innerHTML = html;
          })
          .catch(err => console.error("Errore nel caricamento del footer:", err));

        if (typeof initHeaderInteractions === "function") {
          initHeaderInteractions();
        }
      });
    </script>
</body>
</html>



<?php
require_once __DIR__ . '/includi/admin_guard.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/fanta_old_school.php';

$csrfKey = 'admin_fanta_old_school';
$adminFlash = $_SESSION['fanta_old_school_admin_flash'] ?? null;
unset($_SESSION['fanta_old_school_admin_flash']);

$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'fanta-old-school') ? 'fanta-old-school' : 'strumenti';
$fantaView = (isset($_GET['fos_view']) && $_GET['fos_view'] === 'records') ? 'records' : 'inviti';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fanta_old_school_action'])) {
    csrf_require($csrfKey);

    $leadId = (int)($_POST['lead_id'] ?? 0);
    $action = trim((string)($_POST['fanta_old_school_action'] ?? ''));
    $redirectView = (isset($_POST['fos_view']) && $_POST['fos_view'] === 'inviti') ? 'inviti' : 'records';
    $redirectUrl = login_with_base_path('/admin_dashboard.php?tab=fanta-old-school&fos_view=' . rawurlencode($redirectView));

    if ($leadId <= 0) {
        $_SESSION['fanta_old_school_admin_flash'] = [
            'type' => 'error',
            'message' => 'Record non valido.',
        ];
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($action === 'mark_mail_sent') {
        $updated = fanta_old_school_mark_mail_sent($conn, $leadId, true);
        $_SESSION['fanta_old_school_admin_flash'] = [
            'type' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Campo "Mail inviata" aggiornato.' : 'Impossibile aggiornare il record.',
        ];
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($action === 'clear_mail_sent') {
        $updated = fanta_old_school_mark_mail_sent($conn, $leadId, false);
        $_SESSION['fanta_old_school_admin_flash'] = [
            'type' => $updated ? 'info' : 'error',
            'message' => $updated ? 'Stato mail inviata azzerato.' : 'Impossibile aggiornare il record.',
        ];
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$referralOverview = fanta_old_school_fetch_admin_overview($conn, true);
$formRecords = fanta_old_school_fetch_form_records($conn);
$totalReferralLeads = 0;
$activeReferrers = 0;
$mailsSentCount = 0;

foreach ($formRecords as $record) {
    $totalReferralLeads++;
    if (!empty($record['mail_inviata_il'])) {
        $mailsSentCount++;
    }
}

$activeReferrers = count($referralOverview);
$pendingMailsCount = max(0, $totalReferralLeads - $mailsSentCount);
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
        width: 100%;
        max-width: 1000px;
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
        width: 100%;
        max-width: 1000px;
        min-width: 0;
      }
      .admin-tab-panel.is-active {
        display: block;
      }
      .referral-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 18px;
        width: 100%;
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
        width: 100%;
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
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
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
      .admin-inline-banner {
        width: 100%;
        max-width: 1000px;
        margin: 0 0 16px;
        padding: 14px 16px;
        border-radius: 14px;
        font-weight: 700;
        line-height: 1.45;
      }
      .admin-inline-banner.success {
        background: #ecfdf3;
        color: #0f7a44;
        border: 1px solid #b7ebc7;
      }
      .admin-inline-banner.error {
        background: #fff1f2;
        color: #b91c1c;
        border: 1px solid #fecdd3;
      }
      .admin-inline-banner.info {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
      }
      .fos-admin-nav {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 0 0 18px;
      }
      .fos-admin-btn {
        border: 1px solid #d6e0ea;
        background: #fff;
        color: #15293e;
        border-radius: 999px;
        padding: 10px 14px;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
      }
      .fos-admin-btn.is-active {
        background: #15293e;
        color: #fff;
        border-color: #15293e;
      }
      .fos-admin-btn:hover {
        transform: translateY(-1px);
      }
      .fos-admin-panel {
        display: none;
      }
      .fos-admin-panel.is-active {
        display: block;
      }
      .desktop-only-admin {
        display: block;
      }
      .mobile-only-admin {
        display: none;
      }
      .referral-user-grid,
      .record-card-grid {
        display: grid;
        gap: 14px;
      }
      .referral-user-card,
      .record-card {
        background: #fff;
        border: 1px solid #dbe4ee;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 14px 28px rgba(15, 31, 51, 0.06);
      }
      .referral-user-card h4,
      .record-card h4 {
        margin: 0 0 6px;
        color: #15293e;
      }
      .referral-user-card p,
      .record-card p {
        margin: 0 0 8px;
        color: #4c5b71;
        line-height: 1.5;
      }
      .referral-user-card a,
      .record-card a,
      .referral-table a {
        overflow-wrap: anywhere;
        word-break: break-word;
      }
      .meta-stack {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 10px;
      }
      .meta-chip {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 6px 10px;
        border-radius: 999px;
        background: #edf2f7;
        color: #15293e;
        font-weight: 800;
        font-size: 0.9rem;
      }
      .record-status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: fit-content;
        padding: 7px 11px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.88rem;
      }
      .record-status-pill.sent {
        background: #ecfdf3;
        color: #0f7a44;
        border: 1px solid #b7ebc7;
      }
      .record-status-pill.pending {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fdba74;
      }
      .record-action-form {
        margin-top: 12px;
      }
      .record-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
      }
      .record-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 31, 51, 0.12);
      }
      .record-action-btn.primary {
        background: #15293e;
        color: #fff;
      }
      .record-action-btn.secondary {
        background: #eef4fb;
        color: #15293e;
        border: 1px solid #d6e0ea;
      }
      .record-table .action-cell,
      .record-table td.action-cell {
        white-space: nowrap;
      }
      .referral-link-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
      }
      .referral-link-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 10px;
        font-weight: 800;
        text-decoration: none;
        border: 1px solid #d6e0ea;
        background: #eef4fb;
        color: #15293e;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
      }
      .referral-link-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 18px rgba(15, 31, 51, 0.1);
        background: #e6eef8;
      }
      .referral-link-btn.primary {
        background: #15293e;
        border-color: #15293e;
        color: #fff;
      }
      .referral-link-btn.primary:hover {
        background: #10243a;
      }
      .referral-link-preview {
        display: block;
        margin-top: 8px;
        font-size: 0.85rem;
        color: #64748b;
        overflow-wrap: anywhere;
        word-break: break-word;
      }
      @media (max-width: 768px) {
        .admin-dashboard {
          align-items: stretch;
          padding: 84px 14px 72px;
        }
        .admin-tab-nav {
          gap: 8px;
          margin: 18px 0 16px;
        }
        .admin-tab-btn {
          flex: 1 1 calc(50% - 4px);
          width: 100%;
          text-align: center;
          padding: 10px 12px;
          font-size: 0.94rem;
        }
        .admin-inline-banner {
          margin-bottom: 14px;
        }
        .fos-admin-nav {
          gap: 8px;
        }
        .fos-admin-btn {
          flex: 1 1 calc(50% - 4px);
          text-align: center;
          width: 100%;
        }
        .referral-summary-grid {
          grid-template-columns: 1fr;
        }
        .referral-table-head {
          padding: 18px 16px 10px;
        }
        .referral-table-wrap {
          padding: 0 16px 18px;
        }
        .referral-table {
          min-width: 700px;
        }
        .referral-lead-list {
          min-width: 0;
        }
        .desktop-only-admin {
          display: none;
        }
        .mobile-only-admin {
          display: block;
        }
        .referral-user-card,
        .record-card {
          padding: 16px;
        }
      }
    </style>
</head>
<body class="admin-page">
    <?php include __DIR__ . '/includi/header.php'; ?>

    <main class="admin-dashboard">
        <h1 class="admin-title">Pannello Amministratore</h1>

        <?php if ($adminFlash && !empty($adminFlash['message'])): ?>
          <div class="admin-inline-banner <?= htmlspecialchars((string)($adminFlash['type'] ?? 'info')) ?>">
            <?= htmlspecialchars((string)$adminFlash['message']) ?>
          </div>
        <?php endif; ?>

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
                <h3>Crea Tornei ESPORT</h3>
                <p>Apri il form admin gia impostato sulla sezione ESPORT per pubblicare tornei gaming dedicati.</p><br>
                <a href="/api/gestione_tornei.php?sezione=esport&action=crea">Apri</a>
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
          <span>record raccolti dal form Fanta Old School</span>
        </div>
        <div class="referral-summary-card">
          <strong><?= $activeReferrers ?></strong>
          <span>utenti che hanno gia almeno un invitato</span>
        </div>
        <div class="referral-summary-card">
          <strong><?= $mailsSentCount ?></strong>
          <span>record con campo mail inviata valorizzato</span>
        </div>
        <div class="referral-summary-card">
          <strong><?= $pendingMailsCount ?></strong>
          <span>record ancora da processare</span>
        </div>
      </div>

      <div class="fos-admin-nav" role="tablist" aria-label="Viste Fanta Old School">
        <button
          type="button"
          class="fos-admin-btn <?= $fantaView === 'inviti' ? 'is-active' : '' ?>"
          data-fos-target="fosInvitiPanel"
          aria-selected="<?= $fantaView === 'inviti' ? 'true' : 'false' ?>"
        >Inviti per utente</button>
        <button
          type="button"
          class="fos-admin-btn <?= $fantaView === 'records' ? 'is-active' : '' ?>"
          data-fos-target="fosRecordsPanel"
          aria-selected="<?= $fantaView === 'records' ? 'true' : 'false' ?>"
        >Record form</button>
      </div>

      <section id="fosInvitiPanel" class="fos-admin-panel <?= $fantaView === 'inviti' ? 'is-active' : '' ?>">
        <div class="admin-card referral-table-card">
          <div class="referral-table-head">
            <h3>Inviti per utente</h3>
            <p>Qui vedi solo gli account che hanno invitato almeno una persona, con dettaglio completo dei record raccolti.</p>
          </div>

          <?php if ($referralOverview): ?>
            <div class="referral-table-wrap desktop-only-admin">
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
                          <div class="referral-link-actions">
                            <a class="referral-link-btn primary" href="<?= htmlspecialchars($referralLink) ?>" target="_blank" rel="noopener">Apri link</a>
                            <button type="button" class="referral-link-btn" data-copy-link="<?= htmlspecialchars($referralLink) ?>">Copia</button>
                          </div>
                          <span class="referral-link-preview">/fantaoldschool?ref=<?= htmlspecialchars($referralCode) ?></span>
                        <?php else: ?>
                          <span class="referral-empty">non disponibile</span>
                        <?php endif; ?>
                      </td>
                      <td><strong><?= $leadCount ?></strong></td>
                      <td>
                        <div class="referral-lead-list">
                          <?php foreach (($row['leads'] ?? []) as $lead): ?>
                            <div class="referral-lead-item">
                              <strong><?= htmlspecialchars(trim((string)($lead['nome'] ?? '') . ' ' . (string)($lead['cognome'] ?? ''))) ?></strong>
                              <span><?= htmlspecialchars((string)($lead['email_leghe_fc'] ?? '')) ?></span>
                              <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($lead['created_at'] ?? 'now')))) ?></span>
                              <span>
                                <?php if (!empty($lead['mail_inviata_il'])): ?>
                                  Mail inviata il <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$lead['mail_inviata_il']))) ?>
                                <?php else: ?>
                                  Mail non inviata
                                <?php endif; ?>
                              </span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mobile-only-admin" style="padding: 0 16px 18px;">
              <div class="referral-user-grid">
                <?php foreach ($referralOverview as $row): ?>
                  <?php
                    $referralCode = (string)($row['referral_code'] ?? '');
                    $leadCount = (int)($row['lead_count'] ?? 0);
                    $referralLink = login_with_base_path('/fantaoldschool') . '?ref=' . rawurlencode($referralCode);
                  ?>
                  <article class="referral-user-card">
                    <h4><?= htmlspecialchars((string)($row['label'] ?? 'Utente')) ?></h4>
                    <p><?= htmlspecialchars((string)($row['email'] ?? '')) ?></p>
                    <div class="meta-stack">
                      <span class="meta-chip"><?= $leadCount ?> invitati</span>
                      <?php if ($referralCode !== ''): ?>
                        <span class="meta-chip"><?= htmlspecialchars($referralCode) ?></span>
                        <div class="referral-link-actions">
                          <a class="referral-link-btn primary" href="<?= htmlspecialchars($referralLink) ?>" target="_blank" rel="noopener">Apri link</a>
                          <button type="button" class="referral-link-btn" data-copy-link="<?= htmlspecialchars($referralLink) ?>">Copia</button>
                        </div>
                        <span class="referral-link-preview">/fantaoldschool?ref=<?= htmlspecialchars($referralCode) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="referral-lead-list" style="margin-top:12px;">
                      <?php foreach (($row['leads'] ?? []) as $lead): ?>
                        <div class="referral-lead-item">
                          <strong><?= htmlspecialchars(trim((string)($lead['nome'] ?? '') . ' ' . (string)($lead['cognome'] ?? ''))) ?></strong>
                          <span><?= htmlspecialchars((string)($lead['email_leghe_fc'] ?? '')) ?></span>
                          <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($lead['created_at'] ?? 'now')))) ?></span>
                          <span>
                            <?php if (!empty($lead['mail_inviata_il'])): ?>
                              Mail inviata il <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$lead['mail_inviata_il']))) ?>
                            <?php else: ?>
                              Mail non inviata
                            <?php endif; ?>
                          </span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="referral-table-head">
              <span class="referral-empty">Nessun utente ha ancora invitato persone dal form.</span>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section id="fosRecordsPanel" class="fos-admin-panel <?= $fantaView === 'records' ? 'is-active' : '' ?>">
        <div class="admin-card referral-table-card">
          <div class="referral-table-head">
            <h3>Record form</h3>
            <p>Elenco completo dei record salvati dal form pubblico, con stato mail inviata aggiornabile dal pannello.</p>
          </div>

          <?php if ($formRecords): ?>
            <div class="referral-table-wrap desktop-only-admin">
              <table class="referral-table record-table">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Invitato</th>
                    <th>Email Leghe FC</th>
                    <th>Referral</th>
                    <th>Utente referral</th>
                    <th>Mail inviata</th>
                    <th>Azione</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($formRecords as $record): ?>
                    <?php $mailSent = !empty($record['mail_inviata_il']); ?>
                    <tr>
                      <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($record['created_at'] ?? 'now')))) ?></td>
                      <td>
                        <strong><?= htmlspecialchars(trim((string)($record['nome'] ?? '') . ' ' . (string)($record['cognome'] ?? ''))) ?></strong>
                      </td>
                      <td><?= htmlspecialchars((string)($record['email_leghe_fc'] ?? '')) ?></td>
                      <td><span class="referral-code"><?= htmlspecialchars((string)($record['referral_code'] ?? '')) ?></span></td>
                      <td>
                        <strong><?= htmlspecialchars((string)($record['referrer_label'] ?? ($record['referral_label'] ?? ''))) ?></strong><br>
                        <span><?= htmlspecialchars((string)($record['referrer_email'] ?? '')) ?></span>
                      </td>
                      <td>
                        <?php if ($mailSent): ?>
                          <span class="record-status-pill sent">Si, <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$record['mail_inviata_il']))) ?></span>
                        <?php else: ?>
                          <span class="record-status-pill pending">No</span>
                        <?php endif; ?>
                      </td>
                      <td class="action-cell">
                        <form method="POST" class="record-action-form">
                          <?= csrf_field($csrfKey) ?>
                          <input type="hidden" name="lead_id" value="<?= (int)($record['id'] ?? 0) ?>">
                          <input type="hidden" name="fos_view" value="records">
                          <?php if ($mailSent): ?>
                            <button type="submit" name="fanta_old_school_action" value="clear_mail_sent" class="record-action-btn secondary">Annulla stato</button>
                          <?php else: ?>
                            <button type="submit" name="fanta_old_school_action" value="mark_mail_sent" class="record-action-btn primary">Segna mail inviata</button>
                          <?php endif; ?>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mobile-only-admin" style="padding: 0 16px 18px;">
              <div class="record-card-grid">
                <?php foreach ($formRecords as $record): ?>
                  <?php $mailSent = !empty($record['mail_inviata_il']); ?>
                  <article class="record-card">
                    <h4><?= htmlspecialchars(trim((string)($record['nome'] ?? '') . ' ' . (string)($record['cognome'] ?? ''))) ?></h4>
                    <p><?= htmlspecialchars((string)($record['email_leghe_fc'] ?? '')) ?></p>
                    <div class="meta-stack">
                      <span class="meta-chip"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($record['created_at'] ?? 'now')))) ?></span>
                      <span class="meta-chip"><?= htmlspecialchars((string)($record['referral_code'] ?? '')) ?></span>
                    </div>
                    <p style="margin-top:12px;"><strong>Utente referral:</strong> <?= htmlspecialchars((string)($record['referrer_label'] ?? ($record['referral_label'] ?? ''))) ?></p>
                    <div style="margin-top:8px;">
                      <?php if ($mailSent): ?>
                        <span class="record-status-pill sent">Mail inviata il <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$record['mail_inviata_il']))) ?></span>
                      <?php else: ?>
                        <span class="record-status-pill pending">Mail non inviata</span>
                      <?php endif; ?>
                    </div>
                    <form method="POST" class="record-action-form">
                      <?= csrf_field($csrfKey) ?>
                      <input type="hidden" name="lead_id" value="<?= (int)($record['id'] ?? 0) ?>">
                      <input type="hidden" name="fos_view" value="records">
                      <?php if ($mailSent): ?>
                        <button type="submit" name="fanta_old_school_action" value="clear_mail_sent" class="record-action-btn secondary">Annulla stato</button>
                      <?php else: ?>
                        <button type="submit" name="fanta_old_school_action" value="mark_mail_sent" class="record-action-btn primary">Segna mail inviata</button>
                      <?php endif; ?>
                    </form>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="referral-table-head">
              <span class="referral-empty">Nessun record presente nel form Fanta Old School.</span>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </section>

    <a class="logout-btn" href="index.php">Esci dal pannello</a>
    </main>
    <div id="footer-container"></div>
    <script src="/includi/app.min.js?v=20251220"></script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const tabButtons = document.querySelectorAll(".admin-tab-btn");
        const panels = document.querySelectorAll(".admin-tab-panel");
        const fosButtons = document.querySelectorAll(".fos-admin-btn");
        const fosPanels = document.querySelectorAll(".fos-admin-panel");

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
                nextUrl.searchParams.delete("fos_view");
              }
              window.history.replaceState({}, "", nextUrl.toString());
            } catch (err) {}
          });
        });

        fosButtons.forEach(button => {
          button.addEventListener("click", () => {
            const targetId = button.getAttribute("data-fos-target");
            if (!targetId) return;

            fosButtons.forEach(btn => {
              btn.classList.remove("is-active");
              btn.setAttribute("aria-selected", "false");
            });
            fosPanels.forEach(panel => panel.classList.remove("is-active"));

            button.classList.add("is-active");
            button.setAttribute("aria-selected", "true");

            const targetPanel = document.getElementById(targetId);
            if (targetPanel) {
              targetPanel.classList.add("is-active");
            }

            try {
              const nextUrl = new URL(window.location.href);
              nextUrl.searchParams.set("tab", "fanta-old-school");
              if (targetId === "fosRecordsPanel") {
                nextUrl.searchParams.set("fos_view", "records");
              } else {
                nextUrl.searchParams.delete("fos_view");
              }
              window.history.replaceState({}, "", nextUrl.toString());
            } catch (err) {}
          });
        });

        document.querySelectorAll("[data-copy-link]").forEach(button => {
          button.addEventListener("click", async () => {
            const link = button.getAttribute("data-copy-link");
            if (!link) return;

            const originalLabel = button.textContent;

            try {
              await navigator.clipboard.writeText(link);
              button.textContent = "Copiato";
            } catch (err) {
              const temp = document.createElement("input");
              temp.type = "text";
              temp.value = link;
              document.body.appendChild(temp);
              temp.focus();
              temp.select();
              document.execCommand("copy");
              document.body.removeChild(temp);
              button.textContent = "Copiato";
            }

            window.setTimeout(() => {
              button.textContent = originalLabel;
            }, 1600);
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



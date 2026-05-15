<?php
require_once __DIR__ . '/includi/security.php';
require_once __DIR__ . '/includi/db.php';
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/fanta_old_school.php';

header('X-Robots-Tag: noindex, nofollow', true);

$publicPath = login_with_base_path('/fantaoldschool');
$baseUrl = rtrim(seo_base_url(), '/');
$pageUrl = $baseUrl . $publicPath;
$requestedReferralCode = trim((string)($_GET['ref'] ?? ''));
$normalizedReferralCode = $requestedReferralCode !== '' ? fanta_old_school_normalize_lookup_code($requestedReferralCode) : '';
$isInviteLanding = $normalizedReferralCode !== '';
$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int)($_SESSION['user_id'] ?? 0) : 0;
$currentUser = $currentUserId > 0 ? fanta_old_school_fetch_user_row($conn, $currentUserId) : null;
$currentUserLabel = $currentUser ? fanta_old_school_user_label($currentUser) : trim((string)(($_SESSION['nome'] ?? '') . ' ' . ($_SESSION['cognome'] ?? '')));
$currentUserCode = $currentUserId > 0 ? fanta_old_school_get_referral_code($conn, $currentUserId) : null;
$currentUserLeads = $currentUserId > 0 ? fanta_old_school_fetch_user_leads($conn, $currentUserId) : [];
$referrer = $isInviteLanding ? fanta_old_school_find_referrer($conn, $normalizedReferralCode) : null;
$referrerLabel = $referrer ? fanta_old_school_user_label($referrer) : '';
$shareLink = $currentUserCode ? $pageUrl . '?ref=' . rawurlencode($currentUserCode) : '';
$banner = $_SESSION['fanta_old_school_flash'] ?? null;
$errorMessage = '';
$formValues = [
    'nome' => '',
    'cognome' => '',
    'email_leghe_fc' => '',
];

unset($_SESSION['fanta_old_school_flash']);

$pageTitle = $isInviteLanding && $referrer
    ? 'Fanta Old School - Invito di ' . $referrerLabel
    : 'Fanta Old School - Tornei Old School';
$seo = [
    'title' => $pageTitle,
    'description' => 'Area Fanta Old School con link referral personale e form di iscrizione invitati.',
    'url' => $isInviteLanding ? ($pageUrl . '?ref=' . rawurlencode($normalizedReferralCode)) : $pageUrl,
    'canonical' => $pageUrl,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fanta_old_school'])) {
    $formValues['nome'] = trim((string)($_POST['nome'] ?? ''));
    $formValues['cognome'] = trim((string)($_POST['cognome'] ?? ''));
    $formValues['email_leghe_fc'] = strtolower(trim((string)($_POST['email_leghe_fc'] ?? '')));

    if (!$isInviteLanding || !$referrer) {
        $errorMessage = 'Referral non valido o non piu disponibile.';
    } elseif (!csrf_is_valid($_POST['_csrf'] ?? '', 'fanta_old_school_public_form')) {
        $errorMessage = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } elseif (honeypot_triggered('company_website')) {
        $errorMessage = 'Richiesta non valida.';
    } elseif (!rate_limit_allow('fanta_old_school_public_form', 4, 900)) {
        $wait = rate_limit_retry_after('fanta_old_school_public_form', 900);
        $errorMessage = "Troppi tentativi ravvicinati. Riprova tra {$wait} secondi.";
    } elseif (!captcha_is_valid('fanta_old_school_public_form', $_POST['captcha_answer'] ?? null)) {
        $errorMessage = 'Risposta antispam non valida. Riprova.';
    } elseif ($formValues['nome'] === '' || $formValues['cognome'] === '' || $formValues['email_leghe_fc'] === '') {
        $errorMessage = 'Compila tutti i campi richiesti.';
    } elseif (!filter_var($formValues['email_leghe_fc'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Inserisci una email Leghe FC valida.';
    } else {
        $saveResult = fanta_old_school_create_lead($conn, [
            'utente_referral_id' => (int)($referrer['id'] ?? 0),
            'referral_code' => (string)($referrer['fanta_referral_code'] ?? ''),
            'referral_label' => $referrerLabel,
            'nome' => $formValues['nome'],
            'cognome' => $formValues['cognome'],
            'email_leghe_fc' => $formValues['email_leghe_fc'],
            'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);

        if (($saveResult['status'] ?? '') === 'created') {
            $_SESSION['fanta_old_school_flash'] = [
                'type' => 'success',
                'message' => 'Richiesta inviata. Ti contatteremo partendo dal referral di ' . $referrerLabel . '.',
            ];
            header('Location: ' . $publicPath . '?ref=' . rawurlencode($normalizedReferralCode));
            exit;
        }

        if (($saveResult['status'] ?? '') === 'duplicate') {
            $owner = trim((string)(($saveResult['lead']['referral_label'] ?? '') ?: $referrerLabel));
            $_SESSION['fanta_old_school_flash'] = [
                'type' => 'info',
                'message' => 'Questa email Leghe FC risulta gia registrata' . ($owner !== '' ? ' tramite ' . $owner : '') . '.',
            ];
            header('Location: ' . $publicPath . '?ref=' . rawurlencode($normalizedReferralCode));
            exit;
        }

        $errorMessage = (string)($saveResult['message'] ?? 'Errore durante il salvataggio della richiesta.');
    }
}

$captchaQuestion = $isInviteLanding && $referrer ? captcha_generate('fanta_old_school_public_form') : '';
$leadCount = count($currentUserLeads);
$latestLeadDate = $leadCount > 0 ? (string)($currentUserLeads[0]['created_at'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php render_seo_tags($seo); ?>
  <link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">
  <style>
    body {
      background:
        radial-gradient(circle at top left, rgba(29, 78, 216, 0.08), transparent 32%),
        radial-gradient(circle at top right, rgba(22, 163, 74, 0.08), transparent 28%),
        #f4f7fb;
    }
    .fos-page {
      max-width: 1120px;
      margin: 0 auto;
      padding: 34px 18px 70px;
    }
    .fos-hero {
      background: linear-gradient(135deg, #10243c 0%, #18385d 52%, #0f6d57 100%);
      color: #fff;
      border-radius: 28px;
      padding: 34px 30px;
      box-shadow: 0 28px 60px rgba(15, 31, 51, 0.16);
      position: relative;
      overflow: hidden;
    }
    .fos-hero::after {
      content: "";
      position: absolute;
      inset: auto -120px -120px auto;
      width: 280px;
      height: 280px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
    }
    .fos-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      color: #f8fbff;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 14px;
    }
    .fos-hero h1 {
      margin: 0 0 10px;
      font-size: clamp(2rem, 3vw, 3rem);
      line-height: 1.05;
      max-width: 760px;
      position: relative;
      z-index: 1;
    }
    .fos-hero p {
      margin: 0;
      max-width: 720px;
      color: rgba(248, 251, 255, 0.9);
      font-size: 1.03rem;
      line-height: 1.65;
      position: relative;
      z-index: 1;
    }
    .fos-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 18px;
      margin-top: 24px;
    }
    .fos-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 22px;
      padding: 22px;
      box-shadow: 0 18px 40px rgba(15, 31, 51, 0.08);
    }
    .fos-card h2,
    .fos-card h3 {
      margin: 0 0 12px;
      color: #10243c;
    }
    .fos-card p {
      margin: 0 0 12px;
      color: #4b5d75;
      line-height: 1.6;
    }
    .fos-link-box {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 14px;
    }
    .fos-link-box input {
      flex: 1 1 320px;
      padding: 13px 14px;
      border-radius: 14px;
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: #10243c;
      font-size: 0.98rem;
    }
    .fos-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: none;
      border-radius: 14px;
      padding: 13px 16px;
      font-weight: 800;
      text-decoration: none;
      cursor: pointer;
      transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
    }
    .fos-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(15, 31, 51, 0.12);
    }
    .fos-btn.primary {
      background: linear-gradient(120deg, #10243c, #1c4d78);
      color: #fff;
    }
    .fos-btn.secondary {
      background: #eef4fb;
      color: #10243c;
      border: 1px solid #d7e3f0;
    }
    .fos-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
      margin-top: 18px;
    }
    .fos-stat {
      padding: 16px 18px;
      border-radius: 18px;
      background: linear-gradient(180deg, #f8fbff 0%, #eef5fb 100%);
      border: 1px solid #dde7f2;
    }
    .fos-stat strong {
      display: block;
      color: #10243c;
      font-size: 1.7rem;
      line-height: 1;
      margin-bottom: 8px;
    }
    .fos-stat span {
      color: #4b5d75;
      font-size: 0.95rem;
    }
    .fos-banner {
      margin-top: 22px;
      border-radius: 16px;
      padding: 14px 16px;
      font-weight: 700;
      line-height: 1.5;
    }
    .fos-banner.success {
      background: #ecfdf3;
      color: #0f7a44;
      border: 1px solid #b7ebc7;
    }
    .fos-banner.error {
      background: #fff1f2;
      color: #b91c1c;
      border: 1px solid #fecdd3;
    }
    .fos-banner.info {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }
    .fos-form {
      display: grid;
      gap: 14px;
    }
    .fos-field {
      display: flex;
      flex-direction: column;
      gap: 7px;
    }
    .fos-field label {
      color: #10243c;
      font-weight: 700;
    }
    .fos-field input {
      width: 100%;
      padding: 13px 14px;
      border-radius: 14px;
      border: 1px solid #cbd5e1;
      background: #fff;
      font-size: 1rem;
      color: #10243c;
    }
    .fos-field input:focus {
      outline: none;
      border-color: #1c4d78;
      box-shadow: 0 0 0 3px rgba(28, 77, 120, 0.12);
    }
    .fos-form-note {
      margin: 0;
      color: #64748b;
      font-size: 0.93rem;
      line-height: 1.55;
    }
    .fos-hidden {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
    .fos-table-wrap {
      overflow-x: auto;
    }
    .fos-table {
      width: 100%;
      border-collapse: collapse;
    }
    .fos-table th,
    .fos-table td {
      padding: 12px 10px;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
      vertical-align: top;
    }
    .fos-table th {
      color: #10243c;
      font-size: 0.92rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .fos-table td {
      color: #334155;
    }
    .fos-empty {
      padding: 16px 18px;
      border-radius: 18px;
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      color: #64748b;
    }
    .fos-cta-grid {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .fos-kicker {
      margin-top: 6px;
      color: #64748b;
      font-size: 0.94rem;
    }
    @media (max-width: 720px) {
      .fos-page {
        padding: 20px 14px 56px;
      }
      .fos-hero {
        padding: 26px 20px;
        border-radius: 22px;
      }
      .fos-card {
        padding: 18px;
        border-radius: 18px;
      }
      .fos-link-box {
        flex-direction: column;
      }
      .fos-link-box input {
        flex-basis: auto;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/includi/header.php'; ?>

<main class="fos-page">
  <section class="fos-hero">
    <?php if ($isInviteLanding && $referrer): ?>
      <div class="fos-eyebrow">Invito Fanta Old School</div>
      <h1><?= htmlspecialchars($referrerLabel) ?> ti ha invitato.</h1>
      <p>Compila il form con nome, cognome ed email Leghe FC. Il sistema salva automaticamente il referral del link che hai aperto.</p>
    <?php elseif ($isLoggedIn): ?>
      <div class="fos-eyebrow">Area personale</div>
      <h1>Fanta Old School</h1>
      <p>Da qui generi il tuo link referral, lo condividi e controlli quante persone si sono registrate tramite il tuo invito.</p>
    <?php else: ?>
      <div class="fos-eyebrow">Referral utenti</div>
      <h1>Fanta Old School</h1>
      <p>Accedi con il tuo account per ottenere il link personale da condividere. Se hai ricevuto un invito, apri il link con <code>?ref=...</code> per compilare direttamente il form.</p>
    <?php endif; ?>
  </section>

  <?php if ($banner && !empty($banner['message'])): ?>
    <div class="fos-banner <?= htmlspecialchars((string)($banner['type'] ?? 'info')) ?>">
      <?= htmlspecialchars((string)$banner['message']) ?>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage !== ''): ?>
    <div class="fos-banner error"><?= htmlspecialchars($errorMessage) ?></div>
  <?php endif; ?>

  <?php if ($isInviteLanding): ?>
    <div class="fos-grid">
      <section class="fos-card">
        <h2>Invito ricevuto</h2>
        <?php if ($referrer): ?>
          <p>Stai completando una richiesta associata al referral di <strong><?= htmlspecialchars($referrerLabel) ?></strong>.</p>
          <form class="fos-form" method="POST" action="<?= htmlspecialchars($publicPath . '?ref=' . rawurlencode($normalizedReferralCode)) ?>" autocomplete="off">
            <?= csrf_field('fanta_old_school_public_form') ?>
            <input type="text" name="company_website" class="fos-hidden" tabindex="-1" autocomplete="off">
            <div class="fos-field">
              <label for="fos_nome">Nome</label>
              <input type="text" id="fos_nome" name="nome" maxlength="100" value="<?= htmlspecialchars($formValues['nome']) ?>" required>
            </div>
            <div class="fos-field">
              <label for="fos_cognome">Cognome</label>
              <input type="text" id="fos_cognome" name="cognome" maxlength="100" value="<?= htmlspecialchars($formValues['cognome']) ?>" required>
            </div>
            <div class="fos-field">
              <label for="fos_email_leghe_fc">Email Leghe FC</label>
              <input type="email" id="fos_email_leghe_fc" name="email_leghe_fc" maxlength="190" value="<?= htmlspecialchars($formValues['email_leghe_fc']) ?>" required>
            </div>
            <div class="fos-field">
              <label for="fos_captcha">Verifica antispam: quanto fa <?= htmlspecialchars($captchaQuestion) ?>?</label>
              <input type="text" inputmode="numeric" id="fos_captcha" name="captcha_answer" required>
            </div>
            <p class="fos-form-note">I dati vengono registrati nella nuova area Fanta Old School e collegati all'utente che ti ha invitato. Per dettagli privacy consulta la <a href="<?= htmlspecialchars(login_with_base_path('/privacy.php')) ?>">Privacy Policy</a>.</p>
            <button type="submit" class="fos-btn primary" name="submit_fanta_old_school" value="1">Invia richiesta</button>
          </form>
        <?php else: ?>
          <p>Il codice referral presente nel link non corrisponde a nessun utente attivo.</p>
          <div class="fos-cta-grid">
            <a class="fos-btn secondary" href="<?= htmlspecialchars(login_with_base_path('/index.php')) ?>">Torna alla home</a>
            <a class="fos-btn primary" href="<?= htmlspecialchars(login_with_base_path('/contatti.php')) ?>">Contatta il sito</a>
          </div>
        <?php endif; ?>
      </section>

      <aside class="fos-card">
        <h3>Come funziona</h3>
        <p>1. L'utente condivide il proprio link personale.</p>
        <p>2. Tu compili il form con l'email usata su Leghe FC.</p>
        <p>3. Gli admin vedono subito chi ti ha invitato e possono gestire le adesioni dal pannello amministratore.</p>
      </aside>
    </div>
  <?php elseif ($isLoggedIn): ?>
    <div class="fos-grid">
      <section class="fos-card" style="grid-column: 1 / -1;">
        <h2>Il tuo link personale</h2>
        <p>Condividi questo URL per far compilare il form agli altri utenti. Il referral viene memorizzato in automatico.</p>
        <?php if ($shareLink !== ''): ?>
          <div class="fos-link-box">
            <input type="text" id="fosShareLink" value="<?= htmlspecialchars($shareLink) ?>" readonly>
            <button type="button" class="fos-btn primary" id="fosCopyLinkBtn">Copia link</button>
            <a class="fos-btn secondary" href="<?= htmlspecialchars($shareLink) ?>" target="_blank" rel="noopener">Apri link</a>
          </div>
          <div class="fos-kicker">Referral code: <strong><?= htmlspecialchars((string)$currentUserCode) ?></strong></div>
        <?php else: ?>
          <div class="fos-empty">Non siamo riusciti a generare il tuo referral code. Ricarica la pagina e riprova.</div>
        <?php endif; ?>

        <div class="fos-stats">
          <div class="fos-stat">
            <strong><?= $leadCount ?></strong>
            <span>Invitati registrati</span>
          </div>
          <div class="fos-stat">
            <strong><?= htmlspecialchars($currentUserLabel !== '' ? $currentUserLabel : 'Utente') ?></strong>
            <span>Profilo collegato al referral</span>
          </div>
          <div class="fos-stat">
            <strong><?= htmlspecialchars($latestLeadDate !== '' ? date('d/m/Y', strtotime($latestLeadDate)) : '-') ?></strong>
            <span>Ultima registrazione</span>
          </div>
        </div>
      </section>

      <section class="fos-card" style="grid-column: 1 / -1;">
        <h2>Persone invitate</h2>
        <?php if ($leadCount > 0): ?>
          <div class="fos-table-wrap">
            <table class="fos-table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Cognome</th>
                  <th>Email Leghe FC</th>
                  <th>Data</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($currentUserLeads as $lead): ?>
                  <tr>
                    <td><?= htmlspecialchars((string)($lead['nome'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($lead['cognome'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($lead['email_leghe_fc'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($lead['created_at'] ?? 'now')))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="fos-empty">Non hai ancora invitati registrati. Condividi il tuo link personale per iniziare.</div>
        <?php endif; ?>
      </section>
    </div>
  <?php else: ?>
    <div class="fos-grid">
      <section class="fos-card">
        <h2>Genera il tuo link</h2>
        <p>Ogni utente registrato ha un referral code personale. Entrando nella sezione vedi subito il link da condividere e l'elenco delle persone che hanno compilato il form.</p>
        <div class="fos-cta-grid">
          <a class="fos-btn primary" href="<?= htmlspecialchars(login_with_base_path('/login.php')) ?>">Accedi</a>
          <a class="fos-btn secondary" href="<?= htmlspecialchars(login_with_base_path('/register.php')) ?>">Registrati</a>
        </div>
      </section>

      <aside class="fos-card">
        <h3>Link previsto</h3>
        <p>Il formato del link e <code>torneioldschool.it/fantaoldschool?ref=codice-utente</code> e apre direttamente il form pubblico per chi riceve l'invito.</p>
      </aside>
    </div>
  <?php endif; ?>
</main>

<div id="footer-container"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const footer = document.getElementById('footer-container');
  if (footer) {
    fetch('/includi/footer.html')
      .then(response => response.text())
      .then(html => { footer.innerHTML = html; })
      .catch(err => console.error('Errore nel caricamento del footer:', err));
  }

  const copyBtn = document.getElementById('fosCopyLinkBtn');
  const shareInput = document.getElementById('fosShareLink');
  if (copyBtn && shareInput) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(shareInput.value);
        copyBtn.textContent = 'Link copiato';
        window.setTimeout(() => {
          copyBtn.textContent = 'Copia link';
        }, 1800);
      } catch (err) {
        shareInput.focus();
        shareInput.select();
        document.execCommand('copy');
        copyBtn.textContent = 'Link copiato';
        window.setTimeout(() => {
          copyBtn.textContent = 'Copia link';
        }, 1800);
      }
    });
  }
});
</script>
</body>
</html>

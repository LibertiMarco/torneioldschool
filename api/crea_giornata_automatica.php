<?php
require_once __DIR__ . '/../includi/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Crea giornata automatica</title>
  <link rel="stylesheet" href="/style.min.css?v=20251126">
  <link rel="icon" type="image/png" href="/img/logo_old_school.png">
  <link rel="apple-touch-icon" href="/img/logo_old_school.png">
  <style>
    .auto-matchday-shell {
      padding: 120px 20px 60px;
      background: #f8f9fb;
      min-height: 100vh;
    }
    .auto-matchday-container {
      max-width: 1180px;
      margin: 0 auto;
      background: #fff;
      border-radius: 18px;
      padding: 36px;
      box-shadow: 0 14px 38px rgba(15, 23, 42, 0.10);
    }
    .auto-matchday-intro {
      margin: -12px 0 26px;
      color: #556476;
      line-height: 1.6;
    }
    .auto-grid {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 18px;
    }
    .auto-card {
      grid-column: span 12;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      padding: 22px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }
    .auto-card h2,
    .auto-card h3 {
      margin: 0 0 14px;
      color: #15293e;
    }
    .auto-card p {
      color: #5b6b7d;
    }
    .auto-card--half {
      grid-column: span 6;
    }
    .auto-card--wide {
      grid-column: span 8;
    }
    .auto-card--narrow {
      grid-column: span 4;
    }
    .auto-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 14px 18px;
    }
    .auto-form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 0;
    }
    .auto-form-group label {
      font-weight: 700;
      color: #1c2a3a;
    }
    .auto-form-group input,
    .auto-form-group select,
    .auto-form-group textarea {
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
      border-radius: 10px;
      border: 1px solid #d5dbe4;
      background: #fafbff;
      padding: 12px 14px;
      color: #15293e;
      transition: border-color .2s ease, box-shadow .2s ease;
    }
    .auto-form-group input:focus,
    .auto-form-group select:focus,
    .auto-form-group textarea:focus {
      border-color: #15293e;
      box-shadow: 0 0 0 3px rgba(21, 41, 62, 0.12);
      outline: none;
    }
    .auto-inline-check {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border: 1px solid #d5dbe4;
      border-radius: 12px;
      min-height: 48px;
      background: #f9fbff;
      color: #15293e;
      font-weight: 700;
    }
    .auto-inline-check input {
      width: 18px;
      height: 18px;
      accent-color: #15293e;
    }
    .auto-summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
    }
    .auto-summary-item {
      border: 1px solid #d9e5f1;
      border-radius: 14px;
      padding: 16px;
      background: #f8fbff;
    }
    .auto-summary-item strong {
      display: block;
      color: #15293e;
      font-size: 1.8rem;
      line-height: 1;
      margin-bottom: 8px;
    }
    .auto-summary-item span {
      color: #5b6b7d;
      font-weight: 600;
      line-height: 1.5;
    }
    .auto-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .auto-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #edf2f7;
      color: #15293e;
      font-weight: 700;
      font-size: 0.92rem;
    }
    .auto-team-toolbar,
    .auto-slot-toolbar,
    .auto-preview-toolbar {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
      align-items: center;
    }
    .auto-team-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
    }
    .auto-team-item {
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid #dce4ef;
      border-radius: 12px;
      padding: 12px;
      background: #fbfdff;
    }
    .auto-team-item input {
      width: 18px;
      height: 18px;
      accent-color: #15293e;
      flex-shrink: 0;
    }
    .auto-team-item span {
      color: #15293e;
      font-weight: 600;
      line-height: 1.4;
      min-width: 0;
      word-break: break-word;
    }
    .auto-team-item small {
      color: #5b6b7d;
      font-size: 0.8rem;
      line-height: 1.3;
    }
    .auto-slot-list,
    .auto-availability-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .auto-slot-editor,
    .auto-slot-item,
    .auto-availability-row {
      padding: 14px;
      overflow: hidden;
      border-radius: 14px;
      background: #f8fbff;
      border: 1px solid #dce4ef;
    }
    .auto-slot-editor-grid,
    .auto-availability-row {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 12px;
      align-items: end;
    }
    .auto-slot-editor-grid .auto-form-group:nth-child(1) { grid-column: span 3; }
    .auto-slot-editor-grid .auto-form-group:nth-child(2) { grid-column: span 2; }
    .auto-slot-editor-grid .auto-form-group:nth-child(3) { grid-column: span 4; }
    .auto-slot-editor-grid .auto-form-group:nth-child(4) { grid-column: span 2; }
    .auto-slot-editor-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 12px;
    }
    .auto-slot-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      padding: 10px 12px;
      background: #fbfdff;
    }
    .auto-slot-item-main {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 0;
    }
    .auto-slot-item-title {
      color: #15293e;
      font-weight: 800;
      line-height: 1.2;
      word-break: break-word;
    }
    .auto-slot-item-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .auto-slot-pill {
      display: inline-flex;
      align-items: center;
      padding: 5px 8px;
      border-radius: 999px;
      background: #edf2f7;
      color: #334155;
      font-size: 0.82rem;
      font-weight: 700;
      line-height: 1.2;
    }
    .auto-slot-item .auto-slot-remove {
      padding: 8px 12px;
      flex-shrink: 0;
    }
    .auto-availability-team {
      border: 1px solid #dce4ef;
      border-radius: 16px;
      padding: 14px;
      background: #fbfdff;
    }
    .auto-availability-team header {
      margin-bottom: 10px;
    }
    .auto-availability-team h4 {
      margin: 0;
      color: #15293e;
    }
    .auto-weekday-picker {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 2px;
    }
    .auto-weekday-option {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 10px;
      border: 1px solid #dce4ef;
      border-radius: 999px;
      background: #fff;
      color: #15293e;
      font-weight: 600;
      font-size: 0.9rem;
      line-height: 1.2;
      cursor: pointer;
    }
    .auto-weekday-option input {
      width: 16px;
      height: 16px;
      margin: 0;
      accent-color: #15293e;
      flex-shrink: 0;
    }
    .auto-weekday-help {
      display: block;
      margin-top: 8px;
      color: #6a788c;
      font-size: 0.82rem;
      line-height: 1.4;
    }
    .auto-availability-row .auto-form-group:nth-child(1) { grid-column: span 6; }
    .auto-availability-row .auto-form-group:nth-child(2) { grid-column: span 6; }
    .auto-table-wrap {
      overflow-x: auto;
      border: 1px solid #dce4ef;
      border-radius: 14px;
      background: #fff;
    }
    .auto-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 820px;
    }
    .auto-table th,
    .auto-table td {
      border-bottom: 1px solid #e2e8f0;
      padding: 12px;
      text-align: left;
      vertical-align: top;
    }
    .auto-table th {
      background: #15293e;
      color: #fff;
      font-size: 0.84rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    .auto-table td {
      color: #334155;
      background: #fff;
    }
    .auto-preview-table select,
    .auto-preview-table input {
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
      border-radius: 10px;
      border: 1px solid #d5dbe4;
      background: #fafbff;
      padding: 10px 12px;
      color: #15293e;
    }
    .auto-preview-notes {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 220px;
    }
    .auto-note {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 0.92rem;
      line-height: 1.4;
    }
    .auto-note--warn {
      background: #fff7ed;
      color: #9a3412;
      border: 1px solid #fed7aa;
    }
    .auto-note--error {
      background: #fff1f2;
      color: #b91c1c;
      border: 1px solid #fecdd3;
    }
    .auto-alerts {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .auto-alert {
      padding: 14px 16px;
      border-radius: 14px;
      font-weight: 700;
      line-height: 1.55;
    }
    .auto-alert--info {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
    }
    .auto-alert--success {
      background: #ecfdf3;
      border: 1px solid #b7ebc7;
      color: #0f7a44;
    }
    .auto-alert--error {
      background: #fff1f2;
      border: 1px solid #fecdd3;
      color: #b91c1c;
    }
    .auto-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }
    .auto-btn {
      border: 1px solid transparent;
      border-radius: 12px;
      padding: 12px 18px;
      font-weight: 800;
      cursor: pointer;
      transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
    }
    .auto-btn:hover {
      transform: translateY(-1px);
    }
    .auto-btn-primary {
      background: #15293e;
      border-color: #15293e;
      color: #fff;
      box-shadow: 0 10px 22px rgba(21, 41, 62, 0.20);
    }
    .auto-btn-secondary {
      background: #f5f7fb;
      border-color: #cbd5e1;
      color: #15293e;
    }
    .auto-btn-danger {
      background: #fff1f2;
      border-color: #fecdd3;
      color: #b91c1c;
    }
    .auto-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }
    .auto-empty {
      color: #6a788c;
      font-weight: 600;
    }
    .auto-hidden {
      display: none !important;
    }
    .auto-loading {
      color: #5b6b7d;
      font-weight: 700;
    }
    @media (max-width: 980px) {
      .auto-card--half,
      .auto-card--wide,
      .auto-card--narrow {
        grid-column: span 12;
      }
      .auto-slot-editor-grid .auto-form-group:nth-child(1),
      .auto-slot-editor-grid .auto-form-group:nth-child(2),
      .auto-slot-editor-grid .auto-form-group:nth-child(3),
      .auto-slot-editor-grid .auto-form-group:nth-child(4),
      .auto-availability-row .auto-form-group:nth-child(1),
      .auto-availability-row .auto-form-group:nth-child(2) {
        grid-column: span 12;
      }
    }
    @media (max-width: 768px) {
      .auto-matchday-shell {
        padding: 100px 14px 50px;
      }
      .auto-matchday-container {
        padding: 24px 18px;
      }
      .auto-team-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
      }
      .auto-team-item {
        gap: 8px;
        align-items: flex-start;
        padding: 9px 10px;
        border-radius: 10px;
      }
      .auto-team-item input {
        width: 16px;
        height: 16px;
        margin-top: 2px;
      }
      .auto-team-item span {
        font-size: 0.9rem;
        line-height: 1.25;
      }
      .auto-team-item small {
        display: block;
        margin-top: 2px;
        font-size: 0.72rem;
        line-height: 1.2;
      }
      .auto-slot-editor {
        padding: 12px;
      }
      .auto-slot-editor-actions {
        justify-content: stretch;
      }
      .auto-slot-editor-actions .auto-btn {
        width: 100%;
      }
      .auto-slot-item {
        padding: 8px 10px;
        gap: 8px;
        align-items: flex-start;
      }
      .auto-slot-item-main {
        gap: 4px;
      }
      .auto-slot-item-title {
        font-size: 0.94rem;
      }
      .auto-slot-item-meta {
        gap: 5px;
      }
      .auto-slot-pill {
        padding: 4px 7px;
        font-size: 0.76rem;
      }
      .auto-slot-item .auto-slot-remove {
        padding: 7px 10px;
        border-radius: 10px;
      }
      .auto-availability-team {
        padding: 10px 11px;
        border-radius: 12px;
      }
      .auto-availability-team header {
        margin-bottom: 8px;
      }
      .auto-availability-team h4 {
        font-size: 0.96rem;
        line-height: 1.2;
      }
      .auto-availability-team header p {
        margin-top: 3px !important;
        font-size: 0.76rem;
        line-height: 1.2;
      }
      .auto-availability-row {
        padding: 10px;
        gap: 10px;
        border-radius: 12px;
      }
      .auto-weekday-picker {
        gap: 6px;
      }
      .auto-weekday-option {
        gap: 5px;
        padding: 6px 8px;
        font-size: 0.8rem;
      }
      .auto-weekday-option input {
        width: 14px;
        height: 14px;
      }
      .auto-weekday-help {
        margin-top: 6px;
        font-size: 0.74rem;
        line-height: 1.25;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includi/header.php'; ?>

<main class="auto-matchday-shell">
  <section class="auto-matchday-container">
    <a class="admin-back-link" href="/admin_dashboard.php">Torna alla dashboard</a>
    <h1 class="admin-title">Crea Giornata Automatica</h1>
    <p class="auto-matchday-intro">
      Genera una nuova giornata di regular season partendo dalla classifica attuale, dalle partite già presenti e dagli slot disponibili.
      La preview è sempre modificabile prima del salvataggio definitivo.
    </p>

    <div class="auto-grid">
      <section class="auto-card auto-card--wide">
        <h2>Configurazione</h2>
        <div class="auto-form-grid">
          <div class="auto-form-group">
            <label for="tournamentSelect">Torneo</label>
            <select id="tournamentSelect">
              <option value="">Caricamento tornei...</option>
            </select>
          </div>
          <div class="auto-form-group">
            <label for="giornataInput">Giornata da creare</label>
            <input type="number" id="giornataInput" min="1" value="1">
          </div>
          <div class="auto-form-group">
            <label>Opzioni</label>
            <label class="auto-inline-check">
              <input type="checkbox" id="allowReturnInput">
              <span>Consenti partite di ritorno</span>
            </label>
          </div>
        </div>
      </section>

      <section class="auto-card auto-card--narrow">
        <h2>Messaggi</h2>
        <div id="alertArea" class="auto-alerts">
          <div class="auto-alert auto-alert--info">Seleziona un torneo per caricare squadre, classifica, partite regular season e giornate già create.</div>
        </div>
      </section>

      <section class="auto-card auto-card--half auto-hidden" id="teamsCard">
        <div class="auto-team-toolbar">
          <div>
            <h2>Squadre coinvolte</h2>
            <p style="margin: 6px 0 0;">Solo le squadre selezionate verranno considerate nella generazione automatica.</p>
          </div>
          <div class="auto-actions">
            <button type="button" class="auto-btn auto-btn-secondary" id="selectAllTeamsBtn">Seleziona tutte</button>
            <button type="button" class="auto-btn auto-btn-secondary" id="clearTeamsBtn">Deseleziona tutte</button>
          </div>
        </div>
        <div id="teamsList" class="auto-team-list"></div>
      </section>

      <section class="auto-card auto-card--half auto-hidden" id="slotsCard">
        <div class="auto-slot-toolbar">
          <div>
            <h2>Slot disponibili</h2>
            <p style="margin: 6px 0 0;">Seleziona data, ora, campo e capienza, poi aggiungi lo slot alla lista.</p>
          </div>
        </div>
        <div class="auto-slot-editor">
          <div class="auto-slot-editor-grid">
            <div class="auto-form-group">
              <label for="slotDateInput">Data</label>
              <input type="date" id="slotDateInput">
            </div>
            <div class="auto-form-group">
              <label for="slotTimeInput">Ora</label>
              <input type="time" id="slotTimeInput">
            </div>
            <div class="auto-form-group">
              <label for="slotFieldInput">Campo</label>
              <select id="slotFieldInput"></select>
            </div>
            <div class="auto-form-group">
              <label for="slotQuantityInput">Partite contemporanee</label>
              <input type="number" id="slotQuantityInput" min="1" step="1" value="1">
            </div>
          </div>
          <div class="auto-slot-editor-actions">
            <button type="button" class="auto-btn auto-btn-secondary" id="addSlotBtn">Aggiungi slot selezionato</button>
          </div>
        </div>
        <div id="slotList" class="auto-slot-list"></div>
      </section>

      <section class="auto-card auto-hidden" id="availabilityCard">
        <div class="auto-slot-toolbar">
          <div>
            <h2>Disponibilità opzionali per squadra</h2>
          </div>
        </div>
        <div id="availabilityList" class="auto-availability-list"></div>
      </section>

      <section class="auto-card auto-hidden" id="dataCard">
        <h2>Dati regular season già presenti</h2>
        <div class="auto-grid">
          <div class="auto-card auto-card--wide" style="padding:0;">
            <div style="padding:22px 22px 12px;">
              <h3>Classifica attuale</h3>
            </div>
            <div class="auto-table-wrap" id="classificaWrap"></div>
          </div>
        </div>
      </section>

      <section class="auto-card auto-hidden" id="actionsCard">
        <div class="auto-actions">
          <button type="button" class="auto-btn auto-btn-primary" id="generatePreviewBtn">Genera preview</button>
        </div>
      </section>

      <section class="auto-card auto-hidden" id="previewCard">
        <div class="auto-preview-toolbar">
          <div>
            <h2>Preview partite</h2>
            <p style="margin: 6px 0 0;">Puoi modificare manualmente casa, ospite, data, ora e campo. Ogni cambio viene rivalidato.</p>
          </div>
          <div class="auto-actions">
            <button type="button" class="auto-btn auto-btn-secondary" id="revalidatePreviewBtn">Rivalida preview</button>
            <button type="button" class="auto-btn auto-btn-primary" id="createMatchesBtn" disabled>Crea partite</button>
          </div>
        </div>
        <div id="previewMessageArea" class="auto-alerts" style="margin-bottom: 14px;"></div>
        <div class="auto-table-wrap">
          <table class="auto-table auto-preview-table">
            <thead>
              <tr>
                <th>Squadra casa</th>
                <th>Squadra ospite</th>
                <th>Data</th>
                <th>Ora</th>
                <th>Campo</th>
                <th>Giornata</th>
                <th>Fase</th>
                <th>Avvisi / errori</th>
              </tr>
            </thead>
            <tbody id="previewTableBody">
              <tr><td colspan="8" class="auto-empty">Nessuna preview generata.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</main>

<div id="footer-container"></div>

<script>
  const apiUrl = '/api/crea_giornata_automatica_api.php';
  const state = {
    tournaments: [],
    context: null,
    preview: null,
    selectedTeams: [],
    slots: [],
  };

  const els = {
    alertArea: document.getElementById('alertArea'),
    tournamentSelect: document.getElementById('tournamentSelect'),
    giornataInput: document.getElementById('giornataInput'),
    allowReturnInput: document.getElementById('allowReturnInput'),
    teamsCard: document.getElementById('teamsCard'),
    teamsList: document.getElementById('teamsList'),
    selectAllTeamsBtn: document.getElementById('selectAllTeamsBtn'),
    clearTeamsBtn: document.getElementById('clearTeamsBtn'),
    slotsCard: document.getElementById('slotsCard'),
    slotList: document.getElementById('slotList'),
    slotDateInput: document.getElementById('slotDateInput'),
    slotTimeInput: document.getElementById('slotTimeInput'),
    slotFieldInput: document.getElementById('slotFieldInput'),
    slotQuantityInput: document.getElementById('slotQuantityInput'),
    addSlotBtn: document.getElementById('addSlotBtn'),
    availabilityCard: document.getElementById('availabilityCard'),
    availabilityList: document.getElementById('availabilityList'),
    dataCard: document.getElementById('dataCard'),
    classificaWrap: document.getElementById('classificaWrap'),
    actionsCard: document.getElementById('actionsCard'),
    generatePreviewBtn: document.getElementById('generatePreviewBtn'),
    previewCard: document.getElementById('previewCard'),
    previewTableBody: document.getElementById('previewTableBody'),
    previewMessageArea: document.getElementById('previewMessageArea'),
    revalidatePreviewBtn: document.getElementById('revalidatePreviewBtn'),
    createMatchesBtn: document.getElementById('createMatchesBtn'),
  };

  let previewValidationTimer = null;

  function renderAlerts(items) {
    const list = Array.isArray(items) ? items.filter(Boolean) : [];
    els.alertArea.innerHTML = list.length
      ? list.map(item => `<div class="auto-alert auto-alert--${item.type || 'info'}">${item.text}</div>`).join('')
      : '<div class="auto-alert auto-alert--info">Nessun messaggio.</div>';
  }

  function renderPreviewMessages(items) {
    const list = Array.isArray(items) ? items.filter(Boolean) : [];
    els.previewMessageArea.innerHTML = list.length
      ? list.map(item => `<div class="auto-alert auto-alert--${item.type || 'info'}">${item.text}</div>`).join('')
      : '';
  }

  async function apiRequest(action, options = {}) {
    const method = options.method || 'GET';
    const fetchOptions = {
      method,
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    };

    let url = `${apiUrl}?action=${encodeURIComponent(action)}`;
    if (method === 'GET' && options.params) {
      const params = new URLSearchParams(options.params);
      url += `&${params.toString()}`;
    }

    if (method !== 'GET') {
      fetchOptions.headers['Content-Type'] = 'application/json';
      fetchOptions.body = JSON.stringify(options.body || {});
    }

    const response = await fetch(url, fetchOptions);
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Richiesta non riuscita.');
    }
    return data.data;
  }

  function teamMap() {
    const map = new Map();
    (state.context?.squadre || []).forEach(team => {
      map.set(Number(team.id), team);
    });
    return map;
  }

  function selectedTeamIds() {
    return Array.from(document.querySelectorAll('.auto-team-item input[type="checkbox"]:checked'))
      .map(input => Number(input.value))
      .filter(Number.isFinite);
  }

  function snapshotAvailabilityFromDom() {
    const snapshot = {};
    document.querySelectorAll('.auto-availability-team').forEach(block => {
      const teamId = Number(block.dataset.teamId);
      if (!Number.isFinite(teamId)) {
        return;
      }
      snapshot[teamId] = [];
      block.querySelectorAll('.auto-availability-row').forEach(row => {
        const dates = Array.from(row.querySelectorAll('[data-role="availability-date-option"]:checked'))
          .map(input => input.value || '')
          .filter(Boolean);
        const times = Array.from(row.querySelectorAll('[data-role="availability-time-option"]:checked'))
          .map(input => input.value || '')
          .filter(Boolean);
        snapshot[teamId].push({
          dates,
          times,
        });
      });
    });
    return snapshot;
  }

  function collectSlots() {
    return state.slots.map(slot => ({
      data: slot.data,
      ora: slot.ora,
      campo: slot.campo,
      quantita: slot.quantita,
    }));
  }

  function collectAvailability() {
    return snapshotAvailabilityFromDom();
  }

  function basePayload() {
    return {
      tournament_id: Number(els.tournamentSelect.value || 0),
      giornata: Number(els.giornataInput.value || 0),
      allow_return: els.allowReturnInput.checked,
      selected_team_ids: selectedTeamIds(),
      slots: collectSlots(),
      availability: collectAvailability(),
    };
  }

  function availableFields() {
    const seen = new Set();
    return (state.context?.campi || [])
      .map(field => normalizeFieldValue(field))
      .filter(field => {
        const key = normalizeFieldKey(field);
        if (!field || seen.has(key)) {
          return false;
        }
        seen.add(key);
        return true;
      });
  }

  function normalizeFieldValue(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ');
  }

  function normalizeFieldKey(value) {
    return normalizeFieldValue(value).toLowerCase();
  }

  function fieldSelectOptions(currentValue = '') {
    const fields = availableFields();
    const normalizedCurrentValue = normalizeFieldValue(currentValue);
    const selectedFieldKey = normalizeFieldKey(normalizedCurrentValue);
    const hasCurrentValue = selectedFieldKey !== '' && fields.some(field => normalizeFieldKey(field) === selectedFieldKey);
    const placeholder = fields.length ? '-- Seleziona campo --' : 'Nessun campo disponibile';
    const options = [
      `<option value="" ${selectedFieldKey === '' ? 'selected' : ''}>${placeholder}</option>`,
    ];

    if (selectedFieldKey !== '' && !hasCurrentValue) {
      options.push(`<option value="${escapeAttr(normalizedCurrentValue)}" selected>${escapeHtml(normalizedCurrentValue)}</option>`);
    }

    fields.forEach(field => {
      const selected = normalizeFieldKey(field) === selectedFieldKey ? 'selected' : '';
      options.push(`<option value="${escapeAttr(field)}" ${selected}>${escapeHtml(field)}</option>`);
    });

    return options.join('');
  }

  function renderSlotFieldSelect(currentValue = '') {
    if (!els.slotFieldInput) {
      return;
    }
    const fields = availableFields();
    els.slotFieldInput.innerHTML = fieldSelectOptions(currentValue);
    els.slotFieldInput.disabled = !fields.length;
  }

  function normalizeSlot(slot = {}) {
    return {
      data: String(slot.data || '').trim(),
      ora: String(slot.ora || '').trim(),
      campo: normalizeFieldValue(slot.campo || ''),
      quantita: Math.max(1, parseInt(slot.quantita, 10) || 1),
    };
  }

  function slotIdentity(slot = {}) {
    const normalized = normalizeSlot(slot);
    return [
      normalized.data,
      normalized.ora,
      normalizeFieldKey(normalized.campo),
    ].join('|');
  }

  function readSlotEditorValue() {
    return normalizeSlot({
      data: els.slotDateInput?.value || '',
      ora: els.slotTimeInput?.value || '',
      campo: els.slotFieldInput?.value || '',
      quantita: els.slotQuantityInput?.value || 1,
    });
  }

  function resetSlotEditor(options = {}) {
    const keepDate = options.keepDate ?? false;
    const keepTime = options.keepTime ?? false;
    const keepQuantity = options.keepQuantity ?? false;

    if (els.slotDateInput && !keepDate) {
      els.slotDateInput.value = '';
    }
    if (els.slotTimeInput && !keepTime) {
      els.slotTimeInput.value = '';
    }
    if (els.slotQuantityInput && !keepQuantity) {
      els.slotQuantityInput.value = '1';
    }

    renderSlotFieldSelect('');
  }

  function formatSlotDateLabel(value) {
    const normalized = String(value || '').trim();
    if (!normalized) {
      return '-';
    }

    const date = new Date(`${normalized}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return normalized;
    }

    return date.toLocaleDateString('it-IT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  function formatAvailabilityDateLabel(value) {
    const normalized = String(value || '').trim();
    if (!normalized) {
      return '';
    }

    const date = new Date(`${normalized}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return normalized;
    }

    const weekday = date.toLocaleDateString('it-IT', { weekday: 'long' });
    const dayMonth = date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit' });
    return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${dayMonth}`;
  }

  function availableSlotDateOptions() {
    const seen = new Set();
    return state.slots
      .map(slot => String(slot.data || '').trim())
      .filter(date => {
        if (!date || seen.has(date)) {
          return false;
        }
        seen.add(date);
        return true;
      })
      .sort((left, right) => left.localeCompare(right))
      .map(date => ({
        value: date,
        label: formatAvailabilityDateLabel(date),
      }));
  }

  function formatAvailabilityTimeLabel(value) {
    const normalized = String(value || '').trim();
    return normalized ? normalized.slice(0, 5) : '';
  }

  function availableSlotTimeOptions() {
    const seen = new Set();
    return state.slots
      .map(slot => String(slot.ora || '').trim())
      .filter(time => {
        if (!time || seen.has(time)) {
          return false;
        }
        seen.add(time);
        return true;
      })
      .sort((left, right) => left.localeCompare(right))
      .map(time => ({
        value: time,
        label: formatAvailabilityTimeLabel(time),
      }));
  }

  function upsertSlot(slot) {
    const normalized = normalizeSlot(slot);
    const key = slotIdentity(normalized);
    const existingIndex = state.slots.findIndex(current => slotIdentity(current) === key);

    if (existingIndex >= 0) {
      state.slots[existingIndex] = {
        ...state.slots[existingIndex],
        quantita: state.slots[existingIndex].quantita + normalized.quantita,
      };
      return 'updated';
    }

    state.slots.push(normalized);
    return 'added';
  }

  function normalizeAvailabilityDates(rule = {}) {
    const source = Array.isArray(rule.dates)
      ? rule.dates
      : [];
    const seen = new Set();

    return source
      .map(value => String(value ?? '').trim())
      .filter(value => {
        if (!value || seen.has(value)) {
          return false;
        }
        seen.add(value);
        return true;
      });
  }

  function normalizeAvailabilityTimes(rule = {}) {
    const source = Array.isArray(rule.times)
      ? rule.times
      : [];
    const seen = new Set();

    return source
      .map(value => formatAvailabilityTimeLabel(value))
      .filter(value => {
        if (!value || seen.has(value)) {
          return false;
        }
        seen.add(value);
        return true;
      });
  }

  function mergeAvailabilityRules(rules = []) {
    const mergedDates = new Set();
    const mergedTimes = new Set();

    (Array.isArray(rules) ? rules : []).forEach(rule => {
      normalizeAvailabilityDates(rule).forEach(date => mergedDates.add(date));
      normalizeAvailabilityTimes(rule).forEach(time => mergedTimes.add(time));
    });

    return {
      dates: Array.from(mergedDates),
      times: Array.from(mergedTimes),
    };
  }

  function renderTeams() {
    const teams = state.context?.squadre || [];
    const selected = state.selectedTeams.length ? new Set(state.selectedTeams) : new Set(teams.map(team => Number(team.id)));
    els.teamsList.innerHTML = teams.length
      ? teams.map(team => `
          <label class="auto-team-item">
            <input type="checkbox" value="${Number(team.id)}" ${selected.has(Number(team.id)) ? 'checked' : ''}>
            <span>${escapeHtml(team.nome)}<br><small>${team.punti} pt${team.girone ? ` · Girone ${escapeHtml(team.girone)}` : ''}</small></span>
          </label>
        `).join('')
      : '<p class="auto-empty">Nessuna squadra trovata per il torneo selezionato.</p>';
    state.selectedTeams = selectedTeamIds();
    renderAvailability();
    els.teamsCard.classList.remove('auto-hidden');
  }

  function renderSlots() {
    renderSlotFieldSelect(els.slotFieldInput?.value || '');
    els.slotList.innerHTML = state.slots.length
      ? state.slots.map((slot, index) => `
          <div class="auto-slot-item" data-slot-index="${index}">
            <div class="auto-slot-item-main">
              <div class="auto-slot-item-title">${escapeHtml(slot.campo)}</div>
              <div class="auto-slot-item-meta">
                <span class="auto-slot-pill">${escapeHtml(formatSlotDateLabel(slot.data))}</span>
                <span class="auto-slot-pill">${escapeHtml((slot.ora || '').slice(0, 5) || '-')}</span>
                <span class="auto-slot-pill">${slot.quantita} ${slot.quantita === 1 ? 'partita' : 'partite'}</span>
              </div>
            </div>
            <button type="button" class="auto-btn auto-btn-danger auto-slot-remove" data-slot-index="${index}">Rimuovi</button>
          </div>
        `).join('')
      : '<div class="auto-empty">Nessuno slot aggiunto. Seleziona uno slot e aggiungilo alla lista.</div>';
    els.slotsCard.classList.remove('auto-hidden');
  }

  function addSelectedSlot() {
    const slot = readSlotEditorValue();
    if (!slot.data || !slot.ora || !slot.campo) {
      renderAlerts([{ type: 'error', text: 'Per aggiungere uno slot devi selezionare data, ora e campo.' }]);
      return;
    }

    const action = upsertSlot(slot);
    renderSlots();
    renderAvailability();
    renderAlerts([{
      type: 'info',
      text: action === 'updated'
        ? 'Slot già presente: capienza aggiornata.'
        : 'Slot aggiunto alla lista.',
    }]);
    resetSlotEditor({
      keepDate: true,
      keepTime: true,
      keepQuantity: true,
    });

    if (state.preview) {
      schedulePreviewValidation();
    }
  }

  function addAvailabilityRow(container, rule = {}) {
    const selectedDates = new Set(normalizeAvailabilityDates(rule));
    const dateOptions = availableSlotDateOptions();
    const selectedTimes = new Set(normalizeAvailabilityTimes(rule));
    const timeOptions = availableSlotTimeOptions();
    const row = document.createElement('div');
    row.className = 'auto-availability-row';
    row.innerHTML = `
      <div class="auto-form-group">
        <label>Giorni</label>
        <div class="auto-weekday-picker">
          ${dateOptions.length
            ? dateOptions.map(option => `
                <label class="auto-weekday-option">
                  <input type="checkbox" data-role="availability-date-option" value="${escapeAttr(option.value)}" ${selectedDates.has(option.value) ? 'checked' : ''}>
                  <span>${escapeHtml(option.label)}</span>
                </label>
              `).join('')
            : '<span class="auto-empty">Aggiungi almeno uno slot per scegliere i giorni disponibili.</span>'}
        </div>
      </div>
      <div class="auto-form-group">
        <label>Orari</label>
        <div class="auto-weekday-picker">
          ${timeOptions.length
            ? timeOptions.map(option => `
                <label class="auto-weekday-option">
                  <input type="checkbox" data-role="availability-time-option" value="${escapeAttr(option.value)}" ${selectedTimes.has(option.value) ? 'checked' : ''}>
                  <span>${escapeHtml(option.label)}</span>
                </label>
              `).join('')
            : '<span class="auto-empty">Aggiungi almeno uno slot per scegliere gli orari disponibili.</span>'}
        </div>
      </div>
    `;
    container.appendChild(row);
  }

  function renderAvailability() {
    const selected = selectedTeamIds();
    const teams = teamMap();
    const snapshot = snapshotAvailabilityFromDom();
    state.selectedTeams = selected;

    els.availabilityList.innerHTML = selected.length
      ? ''
      : '<p class="auto-empty">Seleziona almeno una squadra per impostare disponibilità opzionali.</p>';

    selected.forEach(teamId => {
      const team = teams.get(Number(teamId));
      if (!team) {
        return;
      }

      const block = document.createElement('section');
      block.className = 'auto-availability-team';
      block.dataset.teamId = String(teamId);
      block.innerHTML = `
        <header>
          <div>
            <h4>${escapeHtml(team.nome)}</h4>
          </div>
        </header>
        <div class="auto-availability-rows"></div>
      `;

      const rowsWrap = block.querySelector('.auto-availability-rows');
      const rules = Array.isArray(snapshot[teamId]) && snapshot[teamId].length ? snapshot[teamId] : [];
      addAvailabilityRow(rowsWrap, rules.length ? mergeAvailabilityRules(rules) : {});

      els.availabilityList.appendChild(block);
    });

    els.availabilityCard.classList.toggle('auto-hidden', !selected.length);
  }

  function renderClassifica() {
    const rows = state.context?.classifica || [];
    els.classificaWrap.innerHTML = rows.length
      ? `
        <table class="auto-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Squadra</th>
              <th>Pt</th>
              <th>G</th>
              <th>V</th>
              <th>N</th>
              <th>P</th>
              <th>GF</th>
              <th>GS</th>
              <th>DR</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(team => `
              <tr>
                <td>${team.posizione}</td>
                <td>${escapeHtml(team.nome)}</td>
                <td>${team.punti}</td>
                <td>${team.giocate}</td>
                <td>${team.vinte}</td>
                <td>${team.pareggiate}</td>
                <td>${team.perse}</td>
                <td>${team.gol_fatti}</td>
                <td>${team.gol_subiti}</td>
                <td>${team.differenza_reti}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `
      : '<div style="padding:18px;" class="auto-empty">Classifica non disponibile.</div>';
  }

  function previewTeamOptions(currentValue) {
    const selectedIds = selectedTeamIds();
    const teams = teamMap();
    const options = ['<option value="">-- Seleziona --</option>'];
    selectedIds.forEach(teamId => {
      const team = teams.get(Number(teamId));
      if (!team) {
        return;
      }
      options.push(`<option value="${team.id}" ${Number(currentValue) === Number(team.id) ? 'selected' : ''}>${escapeHtml(team.nome)}</option>`);
    });
    return options.join('');
  }

  function renderPreviewRows(rows) {
    if (!Array.isArray(rows) || !rows.length) {
      els.previewTableBody.innerHTML = '<tr><td colspan="8" class="auto-empty">Nessuna preview generata.</td></tr>';
      return;
    }

    els.previewTableBody.innerHTML = rows.map((row, index) => `
      <tr data-row-index="${index}">
        <td>
          <select data-field="home_team_id">${previewTeamOptions(row.home_team_id)}</select>
        </td>
        <td>
          <select data-field="away_team_id">${previewTeamOptions(row.away_team_id)}</select>
        </td>
        <td>
          <input type="date" data-field="data" value="${escapeAttr(row.data || '')}">
        </td>
        <td>
          <input type="time" data-field="ora" value="${escapeAttr((row.ora || '').slice(0, 5))}">
        </td>
        <td>
          <select data-field="campo" ${availableFields().length ? '' : 'disabled'}>
            ${fieldSelectOptions(row.campo || '')}
          </select>
        </td>
        <td>${row.giornata ?? ''}</td>
        <td>Regular season</td>
        <td>
          <div class="auto-preview-notes">
            ${(row.warnings || []).map(note => `<span class="auto-note auto-note--warn">${escapeHtml(note)}</span>`).join('')}
            ${(row.errors || []).map(note => `<span class="auto-note auto-note--error">${escapeHtml(note)}</span>`).join('')}
          </div>
          <input type="hidden" data-field="generated_signature" value="${escapeAttr(row.generated_signature || '')}">
          <input type="hidden" data-field="generated_warnings" value="${escapeAttr(JSON.stringify(row.generated_warnings || []))}">
        </td>
      </tr>
    `).join('');
  }

  function collectPreviewRows() {
    return Array.from(els.previewTableBody.querySelectorAll('tr[data-row-index]')).map(row => {
      let generatedWarnings = [];
      try {
        generatedWarnings = JSON.parse(row.querySelector('[data-field="generated_warnings"]')?.value || '[]');
      } catch (error) {
        generatedWarnings = [];
      }
      return {
        home_team_id: Number(row.querySelector('[data-field="home_team_id"]')?.value || 0),
        away_team_id: Number(row.querySelector('[data-field="away_team_id"]')?.value || 0),
        data: row.querySelector('[data-field="data"]')?.value || '',
        ora: row.querySelector('[data-field="ora"]')?.value || '',
        campo: row.querySelector('[data-field="campo"]')?.value || '',
        generated_signature: row.querySelector('[data-field="generated_signature"]')?.value || '',
        generated_warnings: generatedWarnings,
      };
    });
  }

  function previewAlertPayload(data) {
    const items = [];
    (data.messages || []).forEach(message => {
      items.push({
        type: message.toLowerCase().includes('creat') ? 'success' : (data.valid ? 'info' : 'error'),
        text: message,
      });
    });
    if (!items.length) {
      items.push({
        type: data.valid ? 'success' : 'error',
        text: data.valid ? 'Preview valida: puoi creare le partite.' : 'Preview non valida: correggi gli errori prima del salvataggio.',
      });
    }
    return items;
  }

  function renderPreviewData(data) {
    state.preview = data;
    renderPreviewRows(data.rows || []);
    renderPreviewMessages(previewAlertPayload(data));
    els.previewCard.classList.remove('auto-hidden');
    els.createMatchesBtn.disabled = !data.valid || !(data.rows || []).length;
  }

  async function loadTournaments() {
    try {
      const tournaments = await apiRequest('tournaments');
      state.tournaments = Array.isArray(tournaments) ? tournaments : [];
      els.tournamentSelect.innerHTML = state.tournaments.length
        ? '<option value="">-- Seleziona torneo --</option>' + state.tournaments.map(tournament => `
            <option value="${tournament.id}">${escapeHtml(tournament.nome)}</option>
          `).join('')
        : '<option value="">Nessun torneo disponibile</option>';
      renderAlerts([{ type: 'info', text: 'Seleziona un torneo per iniziare la configurazione.' }]);
    } catch (error) {
      renderAlerts([{ type: 'error', text: error.message }]);
      els.tournamentSelect.innerHTML = '<option value="">Errore caricamento tornei</option>';
    }
  }

  async function loadContext(tournamentId) {
    if (!tournamentId) {
      state.context = null;
      state.preview = null;
      state.selectedTeams = [];
      state.slots = [];
      resetSlotEditor();
      [els.teamsCard, els.slotsCard, els.availabilityCard, els.dataCard, els.actionsCard, els.previewCard].forEach(el => el.classList.add('auto-hidden'));
      return;
    }

    renderAlerts([{ type: 'info', text: 'Caricamento dati del torneo in corso...' }]);
    try {
      const context = await apiRequest('context', {
        params: { tournament_id: tournamentId },
      });
      state.context = context;
      state.preview = null;
      state.selectedTeams = (context.squadre || []).map(team => Number(team.id));
      state.slots = [];
      els.giornataInput.value = Number(context.prossima_giornata || 1);
      resetSlotEditor();
      renderTeams();
      renderSlots();
      renderClassifica();
      els.dataCard.classList.remove('auto-hidden');
      els.actionsCard.classList.remove('auto-hidden');
      els.previewCard.classList.add('auto-hidden');
      renderAlerts([{
        type: 'success',
        text: `Torneo caricato: ${context.torneo?.nome || ''}. Regular season trovata con ${(context.partite_regular || []).length} partite e ${(context.giornate_regular || []).length} giornate.`,
      }]);
    } catch (error) {
      renderAlerts([{ type: 'error', text: error.message }]);
    }
  }

  async function generatePreview() {
    const payload = basePayload();
    if (!payload.tournament_id) {
      renderAlerts([{ type: 'error', text: 'Seleziona un torneo prima di generare la preview.' }]);
      return;
    }

    renderAlerts([{ type: 'info', text: 'Generazione preview in corso...' }]);
    try {
      const data = await apiRequest('preview', {
        method: 'POST',
        body: payload,
      });
      renderPreviewData(data);
      renderAlerts([{ type: data.valid ? 'success' : 'error', text: data.valid ? 'Preview generata correttamente.' : 'Preview generata con avvisi o errori da correggere.' }]);
    } catch (error) {
      renderAlerts([{ type: 'error', text: error.message }]);
    }
  }

  async function validatePreview() {
    if (!state.preview) {
      return;
    }

    try {
      const data = await apiRequest('validate', {
        method: 'POST',
        body: {
          ...basePayload(),
          rows: collectPreviewRows(),
        },
      });
      renderPreviewData(data);
    } catch (error) {
      renderPreviewMessages([{ type: 'error', text: error.message }]);
      els.createMatchesBtn.disabled = true;
    }
  }

  async function savePreview() {
    if (!state.preview) {
      return;
    }

    renderPreviewMessages([{ type: 'info', text: 'Salvataggio partite in corso...' }]);
    try {
      const data = await apiRequest('save', {
        method: 'POST',
        body: {
          ...basePayload(),
          rows: collectPreviewRows(),
        },
      });
      renderPreviewMessages([{ type: 'success', text: data.message || 'Partite create correttamente.' }]);
      renderAlerts([{ type: 'success', text: data.message || 'Partite create correttamente.' }]);
      await loadContext(Number(els.tournamentSelect.value || 0));
    } catch (error) {
      renderPreviewMessages([{ type: 'error', text: error.message }]);
    }
  }

  function schedulePreviewValidation() {
    clearTimeout(previewValidationTimer);
    previewValidationTimer = window.setTimeout(validatePreview, 250);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function escapeAttr(value) {
    return escapeHtml(value);
  }

  els.tournamentSelect.addEventListener('change', () => {
    loadContext(Number(els.tournamentSelect.value || 0));
  });

  els.selectAllTeamsBtn.addEventListener('click', () => {
    document.querySelectorAll('.auto-team-item input[type="checkbox"]').forEach(input => {
      input.checked = true;
    });
    renderAvailability();
  });

  els.clearTeamsBtn.addEventListener('click', () => {
    document.querySelectorAll('.auto-team-item input[type="checkbox"]').forEach(input => {
      input.checked = false;
    });
    renderAvailability();
  });

  els.teamsList.addEventListener('change', event => {
    if (event.target.matches('input[type="checkbox"]')) {
      renderAvailability();
      if (state.preview) {
        schedulePreviewValidation();
      }
    }
  });

  els.addSlotBtn.addEventListener('click', addSelectedSlot);

  els.slotList.addEventListener('click', event => {
    const removeButton = event.target.closest('.auto-slot-remove');
    if (removeButton) {
      const slotIndex = Number(removeButton.dataset.slotIndex || -1);
      if (slotIndex >= 0) {
        state.slots = state.slots.filter((_, index) => index !== slotIndex);
        renderSlots();
        renderAvailability();
      }
      if (state.preview) {
        schedulePreviewValidation();
      }
    }
  });

  els.availabilityList.addEventListener('change', () => {
    if (state.preview) {
      schedulePreviewValidation();
    }
  });

  els.giornataInput.addEventListener('change', () => {
    if (state.preview) {
      schedulePreviewValidation();
    }
  });

  els.allowReturnInput.addEventListener('change', () => {
    if (state.preview) {
      schedulePreviewValidation();
    }
  });

  els.generatePreviewBtn.addEventListener('click', generatePreview);
  els.revalidatePreviewBtn.addEventListener('click', validatePreview);
  els.createMatchesBtn.addEventListener('click', savePreview);

  els.previewTableBody.addEventListener('change', () => {
    schedulePreviewValidation();
  });

  loadTournaments();
  resetSlotEditor();
</script>
</body>
</html>

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
    }
    .auto-slot-list,
    .auto-availability-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .auto-slot-row,
    .auto-availability-row {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 12px;
      align-items: end;
      padding: 14px;
      overflow: hidden;
      border-radius: 14px;
      background: #f8fbff;
      border: 1px solid #dce4ef;
    }
    .auto-slot-row .auto-form-group:nth-child(1) { grid-column: span 3; }
    .auto-slot-row .auto-form-group:nth-child(2) { grid-column: span 2; }
    .auto-slot-row .auto-form-group:nth-child(3) { grid-column: span 4; }
    .auto-slot-row .auto-form-group:nth-child(4) { grid-column: span 2; }
    .auto-slot-row .auto-slot-remove { grid-column: span 1; }
    .auto-availability-team {
      border: 1px solid #dce4ef;
      border-radius: 16px;
      padding: 18px;
      background: #fbfdff;
    }
    .auto-availability-team header {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin-bottom: 14px;
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
    .auto-availability-row .auto-form-group:nth-child(1) { grid-column: span 4; }
    .auto-availability-row .auto-form-group:nth-child(2) { grid-column: span 3; }
    .auto-availability-row .auto-form-group:nth-child(3) { grid-column: span 3; }
    .auto-availability-row .auto-availability-remove { grid-column: span 2; }
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
      .auto-slot-row .auto-form-group:nth-child(1),
      .auto-slot-row .auto-form-group:nth-child(2),
      .auto-slot-row .auto-form-group:nth-child(3),
      .auto-slot-row .auto-form-group:nth-child(4),
      .auto-slot-row .auto-slot-remove,
      .auto-availability-row .auto-form-group:nth-child(1),
      .auto-availability-row .auto-form-group:nth-child(2),
      .auto-availability-row .auto-form-group:nth-child(3),
      .auto-availability-row .auto-availability-remove {
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

      <section class="auto-card auto-hidden" id="contextSummaryCard">
        <h2>Contesto torneo</h2>
        <div class="auto-summary-grid" id="summaryGrid"></div>
        <div style="margin-top: 16px;">
          <strong style="display:block; margin-bottom: 8px; color:#15293e;">Giornate regular già presenti</strong>
          <div id="giornateBadges" class="auto-badges"></div>
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
            <p style="margin: 6px 0 0;">Inserisci data, ora, campo e quante partite contemporanee puo ospitare quello slot.</p>
          </div>
          <button type="button" class="auto-btn auto-btn-secondary" id="addSlotBtn">Aggiungi slot</button>
        </div>
        <div id="slotList" class="auto-slot-list"></div>
      </section>

      <section class="auto-card auto-hidden" id="availabilityCard">
        <div class="auto-slot-toolbar">
          <div>
            <h2>Disponibilità opzionali per squadra</h2>
            <p style="margin: 6px 0 0;">
              Ogni regola può essere: uno o più giorni, solo fascia oraria oppure giorni + fascia oraria. Se una squadra non ha regole,
              può essere assegnata a qualsiasi slot disponibile.
            </p>
          </div>
        </div>
        <div id="availabilityList" class="auto-availability-list"></div>
      </section>

      <section class="auto-card auto-hidden" id="dataCard">
        <h2>Dati regular season già presenti</h2>
        <div class="auto-grid">
          <div class="auto-card auto-card--half" style="padding:0;">
            <div style="padding:22px 22px 12px;">
              <h3>Classifica attuale</h3>
            </div>
            <div class="auto-table-wrap" id="classificaWrap"></div>
          </div>
          <div class="auto-card auto-card--half" style="padding:0;">
            <div style="padding:22px 22px 12px;">
              <h3>Partite regular già create</h3>
            </div>
            <div class="auto-table-wrap" id="matchesWrap"></div>
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
  };
  const weekdayOptions = [
    { value: '1', label: 'Lunedì' },
    { value: '2', label: 'Martedì' },
    { value: '3', label: 'Mercoledì' },
    { value: '4', label: 'Giovedì' },
    { value: '5', label: 'Venerdì' },
    { value: '6', label: 'Sabato' },
    { value: '7', label: 'Domenica' },
  ];

  const els = {
    alertArea: document.getElementById('alertArea'),
    tournamentSelect: document.getElementById('tournamentSelect'),
    giornataInput: document.getElementById('giornataInput'),
    allowReturnInput: document.getElementById('allowReturnInput'),
    summaryCard: document.getElementById('contextSummaryCard'),
    summaryGrid: document.getElementById('summaryGrid'),
    giornateBadges: document.getElementById('giornateBadges'),
    teamsCard: document.getElementById('teamsCard'),
    teamsList: document.getElementById('teamsList'),
    selectAllTeamsBtn: document.getElementById('selectAllTeamsBtn'),
    clearTeamsBtn: document.getElementById('clearTeamsBtn'),
    slotsCard: document.getElementById('slotsCard'),
    slotList: document.getElementById('slotList'),
    addSlotBtn: document.getElementById('addSlotBtn'),
    availabilityCard: document.getElementById('availabilityCard'),
    availabilityList: document.getElementById('availabilityList'),
    dataCard: document.getElementById('dataCard'),
    classificaWrap: document.getElementById('classificaWrap'),
    matchesWrap: document.getElementById('matchesWrap'),
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
        const weekdays = Array.from(row.querySelectorAll('[data-role="weekday-option"]:checked'))
          .map(input => input.value || '')
          .filter(Boolean);
        snapshot[teamId].push({
          weekdays,
          start_time: row.querySelector('[data-field="start_time"]')?.value || '',
          end_time: row.querySelector('[data-field="end_time"]')?.value || '',
        });
      });
    });
    return snapshot;
  }

  function collectSlots() {
    return Array.from(document.querySelectorAll('.auto-slot-row')).map(row => {
      return {
        data: row.querySelector('[data-field="data"]')?.value || '',
        ora: row.querySelector('[data-field="ora"]')?.value || '',
        campo: row.querySelector('[data-field="campo"]')?.value || '',
        quantita: Number(row.querySelector('[data-field="quantita"]')?.value || 1),
      };
    });
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
      .map(field => String(field ?? '').trim())
      .filter(field => {
        const key = field.toLowerCase();
        if (!field || seen.has(key)) {
          return false;
        }
        seen.add(key);
        return true;
      });
  }

  function fieldSelectOptions(currentValue = '') {
    const fields = availableFields();
    const normalizedCurrentValue = String(currentValue ?? '').trim();
    const selectedFieldKey = normalizedCurrentValue.toLowerCase();
    const hasCurrentValue = selectedFieldKey !== '' && fields.some(field => field.toLowerCase() === selectedFieldKey);
    const placeholder = fields.length ? '-- Seleziona campo --' : 'Nessun campo disponibile';
    const options = [
      `<option value="" ${(selectedFieldKey === '' || !hasCurrentValue) ? 'selected' : ''}>${placeholder}</option>`,
    ];

    fields.forEach(field => {
      const selected = field.toLowerCase() === selectedFieldKey ? 'selected' : '';
      options.push(`<option value="${escapeAttr(field)}" ${selected}>${escapeHtml(field)}</option>`);
    });

    return options.join('');
  }

  function normalizeWeekdays(rule = {}) {
    const source = Array.isArray(rule.weekdays)
      ? rule.weekdays
      : (rule.weekday ? [rule.weekday] : []);
    const seen = new Set();

    return source
      .map(value => String(value ?? '').trim())
      .filter(value => {
        if (!/^[1-7]$/.test(value) || seen.has(value)) {
          return false;
        }
        seen.add(value);
        return true;
      });
  }

  function renderSummary() {
    const context = state.context;
    if (!context) {
      els.summaryCard.classList.add('auto-hidden');
      return;
    }

    const matches = context.partite_regular || [];
    const teams = context.squadre || [];
    els.summaryGrid.innerHTML = `
      <div class="auto-summary-item">
        <strong>${teams.length}</strong>
        <span>Squadre nel torneo</span>
      </div>
      <div class="auto-summary-item">
        <strong>${matches.length}</strong>
        <span>Partite già presenti in regular season</span>
      </div>
      <div class="auto-summary-item">
        <strong>${(context.giornate_regular || []).length}</strong>
        <span>Giornate regular già create</span>
      </div>
      <div class="auto-summary-item">
        <strong>${(context.campi || []).length}</strong>
        <span>Campi trovati nella gestione esistente</span>
      </div>
    `;

    const giornate = context.giornate_regular || [];
    els.giornateBadges.innerHTML = giornate.length
      ? giornate.map(giornata => `<span class="auto-badge">Giornata ${giornata}</span>`).join('')
      : '<span class="auto-empty">Nessuna giornata regular ancora presente.</span>';

    els.summaryCard.classList.remove('auto-hidden');
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

  function addSlotRow(slot = {}) {
    const quantity = Math.max(1, parseInt(slot.quantita, 10) || 1);
    const fields = availableFields();
    const wrapper = document.createElement('div');
    wrapper.className = 'auto-slot-row';
    wrapper.innerHTML = `
      <div class="auto-form-group">
        <label>Data</label>
        <input type="date" data-field="data" value="${escapeAttr(slot.data || '')}">
      </div>
      <div class="auto-form-group">
        <label>Ora</label>
        <input type="time" data-field="ora" value="${escapeAttr((slot.ora || '').slice(0, 5))}">
      </div>
      <div class="auto-form-group">
        <label>Campo</label>
        <select data-field="campo" ${fields.length ? '' : 'disabled'}>
          ${fieldSelectOptions(slot.campo || '')}
        </select>
      </div>
      <div class="auto-form-group">
        <label>Partite contemporanee</label>
        <input type="number" min="1" step="1" data-field="quantita" value="${escapeAttr(quantity)}">
      </div>
      <button type="button" class="auto-btn auto-btn-danger auto-slot-remove">Rimuovi</button>
    `;
    els.slotList.appendChild(wrapper);
  }

  function renderSlots() {
    const currentSlots = collectSlots();
    els.slotList.innerHTML = '';
    const slots = currentSlots.length ? currentSlots : [{ data: '', ora: '', campo: '', quantita: 1 }];
    slots.forEach(addSlotRow);
    els.slotsCard.classList.remove('auto-hidden');
  }

  function addAvailabilityRow(container, rule = {}) {
    const selectedWeekdays = new Set(normalizeWeekdays(rule));
    const row = document.createElement('div');
    row.className = 'auto-availability-row';
    row.innerHTML = `
      <div class="auto-form-group">
        <label>Giorni</label>
        <div class="auto-weekday-picker">
          ${weekdayOptions.map(option => `
            <label class="auto-weekday-option">
              <input type="checkbox" data-role="weekday-option" value="${option.value}" ${selectedWeekdays.has(option.value) ? 'checked' : ''}>
              <span>${option.label}</span>
            </label>
          `).join('')}
        </div>
        <small class="auto-weekday-help">Se non selezioni nessun giorno, la regola vale per qualsiasi giorno.</small>
      </div>
      <div class="auto-form-group">
        <label>Dalle</label>
        <input type="time" data-field="start_time" value="${escapeAttr(rule.start_time ? String(rule.start_time).slice(0, 5) : '')}">
      </div>
      <div class="auto-form-group">
        <label>Alle</label>
        <input type="time" data-field="end_time" value="${escapeAttr(rule.end_time ? String(rule.end_time).slice(0, 5) : '')}">
      </div>
      <button type="button" class="auto-btn auto-btn-danger auto-availability-remove">Rimuovi</button>
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
            <p style="margin: 4px 0 0; color:#5b6b7d;">Aggiungi una o più regole opzionali con uno o più giorni preferiti.</p>
          </div>
          <button type="button" class="auto-btn auto-btn-secondary" data-add-availability="${teamId}">Aggiungi disponibilità</button>
        </header>
        <div class="auto-availability-rows"></div>
      `;

      const rowsWrap = block.querySelector('.auto-availability-rows');
      const rules = Array.isArray(snapshot[teamId]) && snapshot[teamId].length ? snapshot[teamId] : [];
      if (rules.length) {
        rules.forEach(rule => addAvailabilityRow(rowsWrap, rule));
      } else {
        addAvailabilityRow(rowsWrap, {});
      }

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

  function renderMatches() {
    const rows = state.context?.partite_regular || [];
    els.matchesWrap.innerHTML = rows.length
      ? `
        <table class="auto-table">
          <thead>
            <tr>
              <th>Giornata</th>
              <th>Casa</th>
              <th>Ospite</th>
              <th>Data</th>
              <th>Ora</th>
              <th>Campo</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(match => `
              <tr>
                <td>${match.giornata ?? '-'}</td>
                <td>${escapeHtml(match.squadra_casa)}</td>
                <td>${escapeHtml(match.squadra_ospite)}</td>
                <td>${escapeHtml(match.data_partita || '')}</td>
                <td>${escapeHtml((match.ora_partita || '').slice(0, 5))}</td>
                <td>${escapeHtml(match.campo || '')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `
      : '<div style="padding:18px;" class="auto-empty">Nessuna partita regular season presente.</div>';
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
      [els.summaryCard, els.teamsCard, els.slotsCard, els.availabilityCard, els.dataCard, els.actionsCard, els.previewCard].forEach(el => el.classList.add('auto-hidden'));
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
      els.giornataInput.value = Number(context.prossima_giornata || 1);
      renderSummary();
      renderTeams();
      renderSlots();
      renderClassifica();
      renderMatches();
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

  els.addSlotBtn.addEventListener('click', () => {
    addSlotRow();
  });

  els.slotList.addEventListener('click', event => {
    if (event.target.matches('.auto-slot-remove')) {
      event.target.closest('.auto-slot-row')?.remove();
      if (!els.slotList.children.length) {
        addSlotRow();
      }
      if (state.preview) {
        schedulePreviewValidation();
      }
    }
  });

  els.slotList.addEventListener('change', () => {
    if (state.preview) {
      schedulePreviewValidation();
    }
  });

  els.availabilityList.addEventListener('click', event => {
    const addButton = event.target.closest('[data-add-availability]');
    if (addButton) {
      const block = addButton.closest('.auto-availability-team');
      const wrap = block?.querySelector('.auto-availability-rows');
      if (wrap) {
        addAvailabilityRow(wrap, {});
      }
      return;
    }

    if (event.target.matches('.auto-availability-remove')) {
      const teamBlock = event.target.closest('.auto-availability-team');
      const wrap = teamBlock?.querySelector('.auto-availability-rows');
      event.target.closest('.auto-availability-row')?.remove();
      if (wrap && !wrap.children.length) {
        addAvailabilityRow(wrap, {});
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
  addSlotRow();
</script>
</body>
</html>

<?php
// Render only the real view, with fixtures. Never execute the database controller.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents(__DIR__ . '/../api/gestione_partite.php');
$view = substr($source, strpos($source, '<!DOCTYPE html>'));
$view = str_replace("<?php include __DIR__ . '/../includi/header.php'; ?>", '', $view);
$torneiDisponibili = [['slug' => 'coppa', 'nome' => 'Coppa Old School', 'sezione' => 'calcio']];
$squadrePerTorneo = ['coppa' => ['A', 'B', 'C', 'D']];
$campiPartita = [['id' => 1, 'nome' => 'Campo 1', 'uso_totale' => 1]];
$partite = [['id' => 1, 'torneo' => 'Altro torneo', 'fase' => 'REGULAR', 'giornata' => 1,
    'squadra_casa' => 'Napoli', 'squadra_ospite' => 'Roma', 'data_partita' => '2026-11-09',
    'ora_partita' => '18:30:00', 'campo' => 'Campo 1', 'giocata' => 0]];
$partiteNonGiocate = $partite; $partiteGiocate = [];
$fasiAmmesse = $fasiAmmesseConBronzo = ['REGULAR', 'GOLD', 'SILVER', 'BRONZO'];
$maxGiornateRegular = 10; $maxGiornateRegularOverrides = []; $tabAttiva = 'crea';
$successo = $errore = $azione = '';
function render_match_field_options($fields): string { return '<option value="">-- Seleziona campo --</option><option value="Campo 1">Campo 1</option>'; }
function csrf_get_token($scope): string { return 'fixture-only'; }
eval('?>' . $view);

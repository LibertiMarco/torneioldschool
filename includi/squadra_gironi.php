<?php
function sanitizeTorneoSlugValue($value) {
    $value = preg_replace('/\.(html?|php)$/i', '', $value);
    $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    return $value;
}

function parseTorneoConfigValue($value): array {
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function buildGironeLabelsList(int $count): array {
    $labels = [];
    for ($i = 0; $i < $count; $i++) {
        $n = $i;
        $label = '';
        do {
            $label = chr(65 + ($n % 26)) . $label;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        $labels[] = $label;
    }
    return $labels;
}

function normalizeGironeValue($value): string {
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/^GIRONE\s+/u', '', $value);
    $value = preg_replace('/^GRUPPO\s+/u', '', $value);
    return substr($value, 0, 32);
}

function torneoHasGironiConfig(array $config): bool {
    $formato = strtolower(trim((string)($config['formato'] ?? $config['formula_torneo'] ?? '')));
    $numeroGironi = max(0, (int)($config['numero_gironi'] ?? 0));

    if ($formato === 'girone') {
        return true;
    }

    if ($formato === 'campionato' || $formato === 'eliminazione') {
        return false;
    }

    return $numeroGironi > 0;
}

function getGironeInfoForTorneo(Torneo $torneoModel, string $torneoSlug): array {
    if ($torneoSlug === '') {
        return ['is_girone' => false, 'labels' => []];
    }

    // Usa la stessa normalizzazione del menu, anche per file .html o slug senza estensione.
    $torneoRow = null;
    $tornei = $torneoModel->getAll();
    if ($tornei) {
        while ($row = $tornei->fetch_assoc()) {
            if (sanitizeTorneoSlugValue($row['filetorneo'] ?? $row['nome'] ?? '') === $torneoSlug) {
                $torneoRow = $row;
                break;
            }
        }
    }
    if (!$torneoRow) {
        return ['is_girone' => false, 'labels' => []];
    }

    $config = parseTorneoConfigValue($torneoRow['config'] ?? null);
    $numeroGironi = max(0, (int)($config['numero_gironi'] ?? 0));

    if (!torneoHasGironiConfig($config) || $numeroGironi <= 0) {
        return ['is_girone' => false, 'labels' => []];
    }

    return [
        'is_girone' => true,
        'labels' => buildGironeLabelsList($numeroGironi),
    ];
}

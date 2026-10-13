<?php
function tournament_format_config(array $config, array $request): array {
    $format = strtolower(trim((string)($config['formato'] ?? '')));
    $config['formato'] = $format;
    if ($format === 'campionato') {
        $config['numero_gironi'] = $config['squadre_per_girone'] = 0;
        if ($config['campionato_squadre'] > 0) $config['totale_squadre'] = $config['campionato_squadre'];
    } elseif ($format === 'girone') {
        $config['campionato_squadre'] = 0;
        $config['totale_squadre'] = $config['numero_gironi'] * $config['squadre_per_girone'];
        if ($config['fase_finale'] !== 'coppe') $config['qualificati_silver'] = $config['qualificati_bronzo'] = 0;
    } elseif ($format === 'eliminazione') {
        $config['campionato_squadre'] = $config['numero_gironi'] = $config['squadre_per_girone'] = 0;
        $config['fase_finale'] = '';
        $config['qualificati_gold'] = $config['qualificati_silver'] = $config['qualificati_bronzo'] = $config['eliminate'] = 0;
        if (isset($request['eliminazione_squadre'])) $config['totale_squadre'] = max(0, (int)$request['eliminazione_squadre']);
    }
    $sections = is_string($request['regole_sezioni'] ?? null)
        ? json_decode($request['regole_sezioni'], true) : null;
    if (is_array($sections)) {
        $config['regole_sezioni'] = [];
        foreach (array_slice($sections, 0, 50) as $section) {
            if (!is_array($section) || !is_string($section['title'] ?? null) || !is_string($section['body'] ?? null)) continue;
            $config['regole_sezioni'][] = ['key' => (string)($section['key'] ?? ''),
                'title' => $section['title'], 'body' => $section['body'], 'auto' => ($section['auto'] ?? false) === true];
        }
    }
    return $config;
}

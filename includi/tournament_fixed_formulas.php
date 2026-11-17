<?php
declare(strict_types=1);

/** Fixed formulas shared by the website, mobile metadata and staff saves. */
function tournament_fixed_formula(string $slug): array
{
    return match (strtolower($slug)) {
        'brasilerao' => ['totale_squadre' => 22, 'campionato_squadre' => 22,
            'qualificati_gold' => 16, 'qualificati_silver' => 4, 'qualificati_bronzo' => 2],
        'laliga' => ['totale_squadre' => 16, 'campionato_squadre' => 16,
            'spareggio_qualificati' => 16, 'qualificati_gold' => 8,
            'qualificati_silver' => 8, 'qualificati_bronzo' => 0],
        default => [],
    };
}

function tournament_apply_fixed_formula(array $config, string $slug): array
{
    $fixed = tournament_fixed_formula($slug);
    if (!$fixed) { return $config; }
    $config = array_replace($config, $fixed, [
        'formato' => 'campionato', 'fase_finale' => 'coppe',
        'numero_gironi' => 0, 'squadre_per_girone' => 0, 'eliminate' => 0,
    ]);
    $title = strtolower($slug) === 'laliga' ? 'Fase 2 - Spareggio' : 'Fase 2 - Coppe';
    $body = strtolower($slug) === 'laliga'
        ? 'Tutte le 16 squadre disputano uno spareggio dopo la Regular Season: 1 vs 16, 2 vs 15, 3 vs 14, 4 vs 13, 5 vs 12, 6 vs 11, 7 vs 10, 8 vs 9. Le 8 vincenti accedono ai quarti di Coppa Gold; le 8 perdenti ai quarti di Coppa Silver.'
        : 'Le squadre dal 1° al 16° posto accedono alla Coppa Gold. Dal 17° al 20° posto accedono alla Coppa Silver. Le ultime due, 21ª e 22ª, accedono alla Coppa Bronzo.';
    // Preserve the other rules, including match duration, awards and schedule.
    foreach (['regole_html', 'regole'] as $field) {
        $text = (string)($config[$field] ?? '');
        if ($field === 'regole' && $text === '') { continue; }
        $text = str_replace("\r\n", "\n", $text);
        $pattern = '/^\s*Fase\s*2\s*[-–—]\s*(?:Coppe|Playoff|Spareggio)\s*:[\s\S]*?(?=\n\s*\n|$)/imu';
        $replacement = $title . ': ' . $body;
        $config[$field] = preg_match($pattern, $text)
            ? preg_replace($pattern, $replacement, $text)
            : trim($text . "\n\n" . $replacement);
    }
    if (isset($config['regole_sezioni']) && is_array($config['regole_sezioni'])) {
        $found = false;
        foreach ($config['regole_sezioni'] as &$section) {
            if (preg_match('/^Fase\s*2\b/iu', (string)($section['title'] ?? ''))) {
                $section['title'] = $title;
                $section['body'] = $body;
                $found = true;
            }
        }
        unset($section);
        if (!$found) { $config['regole_sezioni'][] = ['title' => $title, 'body' => $body]; }
    }
    return $config;
}

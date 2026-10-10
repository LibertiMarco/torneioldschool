<?php
declare(strict_types=1);

/** Read only the public presentation settings from the tournament's own files.
 * Never execute a page or JavaScript while answering the mobile metadata API.
 */
function mobile_tournament_layout(string $slug): array
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $slug)) {
        throw new InvalidArgumentException('Slug non valido.');
    }
    $source = '';
    foreach (glob(__DIR__ . '/../tornei/script-*.js') ?: [] as $path) {
        if (strcasecmp(basename($path), 'script-' . $slug . '.js') === 0) {
            $source = (string)file_get_contents($path);
            break;
        }
    }
    if ($source === '') {
        $source = (string)file_get_contents(__DIR__ . '/../tornei/script-TorneoTemplate.js');
    }
    $constants = [];
    preg_match_all('/\bconst\s+([A-Z_0-9]+)\s*=\s*(\d+)\s*;/', $source, $found, PREG_SET_ORDER);
    foreach ($found as $item) { $constants[$item[1]] = (int)$item[2]; }
    $modern = str_contains($source, 'const CONFIG =');
    $goldParts = array_intersect_key($constants, array_flip(['GOLD_SEMI_SPOTS','GOLD_QUARTI_SPOTS','GOLD_OTTAVI_SPOTS']));
    $goldSingle = $constants['GOLD_CUTOFF'] ?? $constants['GOLD_SLOTS'] ?? $constants['GOLD_SPOTS'] ?? ($goldParts ? array_sum($goldParts) : 16);
    $goldGrouped = $goldSingle;
    if (preg_match('/fallbackGoldSpots:\s*(\d+)\s*,/', $source, $goldFallback)) { $goldGrouped = (int)$goldFallback[1]; }
    $silverSingle = $constants['SILVER_SPOTS'] ?? null;
    if (isset($constants['SILVER_START'], $constants['SILVER_END'])) {
        $silverSingle = max(0, $constants['SILVER_END'] - $constants['SILVER_START'] + 1);
    } elseif (str_contains($source, 'const SILVER_SPOTS = TEAM_COUNT -')) {
        $silverSingle = max(0, ($constants['TEAM_COUNT'] ?? 18) - $goldSingle);
    }
    $rules = '';
    // Legacy pages store their rules as static HTML instead of config.regole_html.
    $page = __DIR__ . '/../tornei/' . $slug . '.php';
    if (is_file($page) && class_exists('DOMDocument')) {
        $html = preg_replace('/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/', '', (string)file_get_contents($page));
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);
        $box = $xpath->query('//*[@id="regole"]//*[contains(concat(" ", normalize-space(@class), " "), " regole-box ")]')->item(0);
        if ($box !== null) {
            foreach ($box->childNodes as $child) { $rules .= $doc->saveHTML($child); }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    return [
        'group_mode' => $modern ? 'template' : 'legacy',
        'default_team_count' => $constants['DEFAULT_TEAM_COUNT'] ?? $constants['TEAM_COUNT'] ?? 18,
        'default_gold' => $constants['DEFAULT_GOLD'] ?? 16,
        'default_silver' => $constants['DEFAULT_SILVER'] ?? 2,
        'default_bronze' => $constants['DEFAULT_BRONZE'] ?? 0,
        'legacy_gold' => $goldSingle,
        'legacy_group_gold' => $goldGrouped,
        'legacy_silver' => $silverSingle,
        'legacy_silver_at_end' => str_contains($source, 'posizione > teamCount - SILVER_SPOTS'),
        'gold_per_group_override' => $constants['GOLD_POSITIONS_PER_GROUP'] ?? null,
        'gold_bands' => $goldParts,
        'spareggio' => str_contains($source, 'USE_SPAREGGIO_SEEDING'),
        'spareggio_default' => $constants['DEFAULT_SPAREGGIO_SPOTS'] ?? 16,
        'eliminated_color' => str_contains($source, 'eliminated-row'),
        'rules_html' => trim($rules),
    ];
}

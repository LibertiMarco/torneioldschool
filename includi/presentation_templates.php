<?php
require_once __DIR__ . '/graphics_templates.php';

// Ordered, explicit identities for the competitions represented by the site's pages.
function presentation_template_catalog(): array
{
    return [
        'brasileirao' => ['brasilerao', 'brasileirao', 'brasileiro'],
        'conference' => ['conference'],
        'champions-a' => ['championsleaguea'],
        'champions-b' => ['championsleagueb'],
        'champions' => ['champions'],
        'europa' => ['europa'],
        'night-world-2' => ['allinonenightmondiale2'],
        'night-world' => ['allinonenightmondiale'],
        'night-premier' => ['allinonenightpremier'],
        'night-italy' => ['allinonenightseriea'],
        'world-a' => ['mondialefasciaa'],
        'world-b' => ['mondialefasciab'],
        'short-world' => ['shorteditionmondiale'],
        'intercontinental' => ['legaintercontinental', 'intercontinental'],
        'world' => ['mondiale', 'worldcup'],
        'serie-a' => ['seriea'], 'serie-b' => ['serieb'], 'serie-c' => ['seriec'],
        'bundesliga' => ['bundesliga'], 'premier' => ['premier'],
        'primera' => ['primeradivision', 'laliga'], 'portugal' => ['portugal'],
        'ligue' => ['ligue'], 'eredivisie' => ['eredivisie'],
        'saudi' => ['saudi'], 'africa' => ['coppadafrica', 'africacup'],
        'christmas' => ['christmass', 'christmas', 'natale'],
        'formula' => ['formula1'], 'esport' => ['eafc', 'esport'],
        'mcleague' => ['mcleague'], 'supercup' => ['supercup', 'supercoppa'],
        'weekend' => ['weekend'], 'editorial' => [],
    ];
}

function presentation_template_guess(array $tournament): string
{
    foreach ([$tournament['filetorneo'] ?? '', $tournament['nome']] as $name) {
        if (function_exists('iconv')) $name = iconv('UTF-8', 'ASCII//TRANSLIT', (string)$name) ?: $name;
        $value = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$name));
        foreach (presentation_template_catalog() as $template => $aliases) {
            foreach ($aliases as $alias) if (strpos($value, $alias) !== false) return $template;
        }
    }
    return 'editorial';
}

function presentation_template_for(array $tournament, string $runtimeDir, string $localDir, array $assignments = []): string
{
    $id = (int)$tournament['id'];
    if ($id < 1) throw new InvalidArgumentException('Identificativo torneo non valido.');
    $catalog = presentation_template_catalog();
    $assigned = $assignments[(string)$id] ?? null;
    if (is_string($assigned) && isset($catalog[$assigned])) return $assigned;
    // An existing config JSON can explicitly associate new competitions without schema changes.
    $config = json_decode($tournament['config'] ?? '{}', true) ?: [];
    $override = $config['presentation_template'] ?? null;
    if (is_string($override) && isset($catalog[$override])) return $override;
    $runtime = $runtimeDir . '/' . $id . '-presentation.json';
    $local = $localDir . '/' . $id . '-presentation.json';
    $saved = graphics_template_load($runtime, $local);
    if (isset($catalog[$saved['template'] ?? ''])) return $saved['template'];
    $template = presentation_template_guess($tournament);
    // Persist by primary key: future renames and other teams cannot change the identity.
    graphics_template_store($runtime, $local, ['template' => $template]);
    return $template;
}

function presentation_tournament_key(string $value): string
{
    return preg_replace('/\.(php|html)$/i', '', trim($value));
}

function presentation_team_tournament_id(string $code, array $tournaments): ?int
{
    // Exact matches take precedence over extension-stripped file slugs.
    foreach ($tournaments as $t) {
        if (strcasecmp($code, $t['nome']) === 0 || strcasecmp($code, $t['filetorneo']) === 0) return (int)$t['id'];
    }
    foreach ($tournaments as $t) {
        if (strcasecmp(presentation_tournament_key($code), presentation_tournament_key($t['filetorneo'])) === 0) return (int)$t['id'];
    }
    return null;
}
